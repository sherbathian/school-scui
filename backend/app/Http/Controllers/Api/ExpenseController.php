<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\School;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function heads(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'view');

        return response()->json(['data' => $record->expenseHeads()->withCount('expenses')->orderBy('title')->get()]);
    }

    public function storeHead(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150', Rule::unique('expense_heads', 'title')->where(fn (Builder $query) => $query->where('school_id', $record->id))],
            'description' => ['nullable', 'string'],
        ]);

        return response()->json(['data' => $record->expenseHeads()->create($validated)], 201);
    }

    public function updateHead(Request $request, int $school, int $head): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $headRecord = $record->expenseHeads()->findOrFail($head);
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:150', Rule::unique('expense_heads', 'title')->where(fn (Builder $query) => $query->where('school_id', $record->id))->ignore($headRecord->id)],
            'description' => ['nullable', 'string'],
        ]);
        $headRecord->update($validated);

        return response()->json(['data' => $headRecord->refresh()->loadCount('expenses')]);
    }

    public function destroyHead(Request $request, int $school, int $head): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $headRecord = $record->expenseHeads()->findOrFail($head);
        abort_if($headRecord->expenses()->exists(), 422, 'This expense head is in use and cannot be deleted.');
        $headRecord->delete();

        return response()->json([], 204);
    }

    public function index(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'view');
        $query = $record->expenses()->with('head:id,title')->orderByDesc('expense_date')->orderByDesc('id');
        $filters = $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from']]);
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($filter) use ($search) {
                $filter->where('name', 'like', "%{$search}%")
                    ->orWhere('invoice_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('head', fn ($head) => $head->where('title', 'like', "%{$search}%"));
            });
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('expense_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('expense_date', '<=', $filters['date_to']);
        }

        return response()->json(['data' => $query->get()->map(fn (Expense $expense): array => $this->expenseData($expense))->values()]);
    }

    public function store(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $validated = $request->validate($this->expenseRules($record));
        $file = $validated['attachment'] ?? null;
        unset($validated['attachment']);
        if ($file) {
            $validated['attachment_path'] = $file->store("expenses/{$school}", 'local');
        }
        $expense = $record->expenses()->create($validated)->load('head:id,title');

        return response()->json(['data' => $this->expenseData($expense)], 201);
    }

    public function update(Request $request, int $school, int $expense): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $expenseRecord = $record->expenses()->findOrFail($expense);
        $validated = $request->validate($this->expenseRules($record, true));
        $file = $validated['attachment'] ?? null;
        unset($validated['attachment']);
        if ($file) {
            if ($expenseRecord->attachment_path) {
                Storage::disk('local')->delete($expenseRecord->attachment_path);
            }
            $validated['attachment_path'] = $file->store("expenses/{$school}", 'local');
        }
        $expenseRecord->update($validated);

        return response()->json(['data' => $this->expenseData($expenseRecord->refresh()->load('head:id,title'))]);
    }

    public function destroy(Request $request, int $school, int $expense): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $expenseRecord = $record->expenses()->findOrFail($expense);
        if ($expenseRecord->attachment_path) {
            Storage::disk('local')->delete($expenseRecord->attachment_path);
        }
        $expenseRecord->delete();

        return response()->json([], 204);
    }

    public function attachment(int $school, int $expense): StreamedResponse
    {
        $expenseRecord = Expense::query()->where('school_id', $school)->findOrFail($expense);
        abort_unless($expenseRecord->attachment_path && Storage::disk('local')->exists($expenseRecord->attachment_path), 404);

        return Storage::disk('local')->response($expenseRecord->attachment_path);
    }

    private function expenseRules(School $school, bool $partial = false): array
    {
        return [
            'expense_head_id' => [$partial ? 'sometimes' : 'required', 'integer', Rule::exists('expense_heads', 'id')->where('school_id', $school->id)],
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'invoice_number' => ['nullable', 'string', 'max:100', Rule::unique('expenses', 'invoice_number')->where(fn (Builder $query) => $query->where('school_id', $school->id))->ignore(request()->route('expense'))],
            'expense_date' => [$partial ? 'sometimes' : 'required', 'date'],
            'amount' => [$partial ? 'sometimes' : 'required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /** @return array<string, mixed> */
    private function expenseData(Expense $expense): array
    {
        return [...$expense->only(['id', 'expense_head_id', 'name', 'description', 'invoice_number', 'amount', 'attachment_path']),
            'expense_date' => $expense->expense_date?->toDateString(),
            'expense_head' => $expense->head?->title,
            'attachment_url' => $expense->attachment_path ? URL::temporarySignedRoute('expenses.attachment', now()->addMinutes(30), ['school' => $expense->school_id, 'expense' => $expense->id], absolute: false) : null,
        ];
    }

    private function accessibleSchool(Request $request, int $school, string $ability): School
    {
        $schools = $request->user()->isSuperAdmin() ? School::query() : $request->user()->activeSchools();
        $record = $schools->whereKey($school)->firstOrFail();
        Gate::authorize($ability, $record);

        return $record;
    }
}
