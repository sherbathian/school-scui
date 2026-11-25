<?php

namespace App\Console\Commands;

use App\Services\BillingReconciliationPreview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OverflowException;

class PreviewBillingReconciliation extends Command
{
    protected $signature = 'billing:reconcile-preview {branch : School branch ID} {--as-of= : Inclusive YYYY-MM-DD cutoff}';

    protected $description = 'Preview separate billing source totals on an isolated database copy';

    public function handle(BillingReconciliationPreview $preview): int
    {
        $branch = filter_var($this->argument('branch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($branch === false) {
            $this->error('Provide a positive branch ID.');

            return self::FAILURE;
        }

        $date = $this->option('as-of');
        if (! is_string($date) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $parts)
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            $this->error('Provide a valid cutoff date in YYYY-MM-DD format.');

            return self::FAILURE;
        }

        if (! DB::table('schools')->where('id', $branch)->exists()) {
            $this->error('Branch not found.');

            return self::FAILURE;
        }

        try {
            $result = $preview->forBranch($branch, $date);
        } catch (OverflowException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->output->writeln(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
