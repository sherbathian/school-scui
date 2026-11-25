import { Catalogue } from './Directory.jsx';
import '../../../css/branch-workspace.css';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api } from '../../admin-api.js';
import { NavigationGroup } from '../../components/admin-navigation.jsx';
import { BranchPicker } from '../../components/branch-picker.jsx';
import { AdminLayout } from '../../components/admin-layout.jsx';
import AcademicStructure from './branch/AcademicStructure.jsx';
import ExpenseManagement from './branch/ExpenseManagement.jsx';
import FeeManagement from './branch/FeeManagement.jsx';
import FeeSetup from './branch/FeeSetup.jsx';
import FinancePilot from './branch/FinancePilot.jsx';
import GuardianManagement from './branch/GuardianManagement.jsx';
import SchoolSessions from './branch/SchoolSessions.jsx';
import StaffManagement from './branch/StaffManagement.jsx';
import StudentInquiries from './branch/StudentInquiries.jsx';
import StudentRoster from './branch/StudentRoster.jsx';

const sections = [
    ['academics', 'Academics', ['grades', 'Grades'], ['sections', 'Sections'], ['subjects', 'Subjects'], ['sessions', 'Academic years']],
    ['students', 'Students', ['students', 'Student roster'], ['student-transfers', 'Student transfers']],
    ['attendance', 'Attendance', ['attendance', 'Attendance register']],
    ['guardians', 'Guardians', ['guardians', 'Guardian records']],
    ['inquiries', 'Admissions', ['inquiries', 'Student inquiries']],
    ['staff', 'Staff', ['staff-directory', 'Staff directory'], ['staff-create', 'Add staff'], ['staff-departments', 'Departments'],
        ['staff-designations', 'Designations'], ['staff-leave-types', 'Leave types']],
    ['fees', 'Fees', ['fee-types', 'Fee types'], ['discounts', 'Fee discounts'], ['student-fee-discounts', 'Student fee discounts'], ['groups', 'Fee groups'],
        ['masters', 'Fee masters & charges'], ['fee-items', 'Legacy fee items'], ['invoices', 'Invoices & payments']],
    ['expenses', 'Expenses', ['expense-heads', 'Expense heads'], ['expense-add', 'Add expenses'], ['expense-search', 'Expenses list']],
];

export default function BranchWorkspace(props) {
    return <Workspace key={props.branch?.id ?? 'choose'} {...props} />;
}

function Workspace({ branch, branches, adminName, platform = true, schoolAccess, allowedModules, financePilotGrants = [], academicsEnabled: branchAcademicsEnabled }) {
    const [recordBusy, setRecordBusy] = useState(false);
    const [recordFeedback, setRecordFeedback] = useState(null);
    const [school, setSchool] = useState(null);
    const [modules, setModules] = useState(platform ? [] : allowedModules);
    const [view, setView] = useState('');
    const [error, setError] = useState('');
    const [signingOut, setSigningOut] = useState(false);
    const [student, setStudent] = useState(null);
    const [inquiry, setInquiry] = useState(null);

    useEffect(() => {
        if (!branch) return;
        const controller = new AbortController();
        const get = path => api(path, 'GET', undefined, controller.signal);
        Promise.all([get(`schools/${branch.id}`), platform ? get(`admin/branches/${branch.id}/modules`) : null])
            .then(([schoolResult, moduleResult]) => {
                setSchool(schoolResult.data);
                setModules(moduleResult ? moduleResult.data.filter(module => module.enabled).map(module => module.slug) : allowedModules);
            })
            .catch(error => {
                if (!controller.signal.aborted) setError(error.message);
            });
        return () => controller.abort();
    }, [branch, platform, allowedModules]);

    const visible = sections.filter(([module]) => modules.includes(module))
        .map(([key, label, ...views]) => [key, label, ...views.filter(([view]) =>
            platform || (!['student-transfers', 'student-fee-discounts'].includes(view)
                && (branchAcademicsEnabled || !['groups', 'masters'].includes(view))))]);
    if (financePilotGrants.length) {
        visible.push(['finance-pilot', 'Finance pilot', ['finance-pilot', 'Receipt workspace']]);
    }
    const selected = visible.find(([, , ...views]) => views.some(([key]) => key === view)) ?? visible[0];
    const [module, , firstView] = selected ?? [];
    const activeView = selected?.slice(2).some(([key]) => key === view) ? view : firstView?.[0];

    function navigate(next) {
        setRecordFeedback(null);
        setStudent(null);
        setInquiry(null);
        setView(next);
    }

    function openStudent(record) {
        setView('students');
        setStudent(record.id);
    }

    async function registerInquiry(source, record) {
        await api(`schools/${branch.id}/student-inquiries/${source.id}`, 'PATCH', { status: 'registered', student_id: record.id });
        setInquiry(null);
    }

    async function signOut() {
        setSigningOut(true);
        try {
            await api('logout', 'POST');
            window.location.assign('/admin/login');
        } catch (error) {
            setError(error.message);
            setSigningOut(false);
        }
    }

    async function runRecordAction(action) {
        setRecordBusy(true);
        setRecordFeedback(null);
        try {
            setRecordFeedback({ message: await action() });
        } catch (error) {
            setRecordFeedback({ error: true, message: error.message });
        } finally {
            setRecordBusy(false);
        }
    }

    const branchRecords = ['student-transfers', 'student-fee-discounts'].includes(activeView);
    let content;
    if (school && activeView) {
        const props = { school, canManage: true };
        const academicsEnabled = platform ? modules.includes('academics') : branchAcademicsEnabled;
        if (branchRecords) {
            content = <>
                {recordFeedback && <p id="record-feedback" role="status" data-error={recordFeedback.error || undefined}>{recordFeedback.message}</p>}
                <Catalogue key={activeView} resource={activeView} schoolId={branch.id} branchScoped
                    title={activeView === 'student-transfers' ? 'Student transfers' : 'Student fee discounts'}
                    busy={recordBusy} run={runRecordAction} setFeedback={setRecordFeedback} />
            </>;
        } else switch (module) {
            case 'academics':
                content = activeView === 'sessions' ? <SchoolSessions {...props}
                    onCurrentChange={session => setSchool(current => ({
                        ...current,
                        active_session: session && {
                            ...session,
                            academic_year: `${session.starts_on.slice(0, 4)}-${session.ends_on.slice(0, 4)}`,
                        },
                    }))} />
                    : <AcademicStructure {...props} expanded standalone activeSection={activeView} />;
                break;
            case 'students':
                content = <StudentRoster {...props} expanded standalone canTakeAttendance={academicsEnabled && modules.includes('attendance')}
                    academicsEnabled={academicsEnabled} guardiansEnabled={modules.includes('guardians')}
                    selectedStudentId={student} onOpenStudent={openStudent} onCloseStudent={() => setStudent(null)}
                    onNavigate={navigate} admissionSeed={inquiry} onAdmissionComplete={registerInquiry} onAdmissionCancel={() => setInquiry(null)} />;
                break;
            case 'attendance':
                content = academicsEnabled
                    ? <StudentRoster {...props} expanded standalone attendanceOnly canTakeAttendance />
                    : <p>Enable Academic setup to select grades for attendance.</p>;
                break;
            case 'guardians':
                content = <GuardianManagement {...props} onNavigate={navigate} onOpenStudent={modules.includes('students') ? openStudent : undefined} />;
                break;
            case 'inquiries':
                content = <StudentInquiries {...props} academicsEnabled={academicsEnabled} onStartAdmission={modules.includes('students') ? record => { setInquiry(record); setView('students'); } : undefined} />;
                break;
            case 'staff':
                content = <StaffManagement {...props} academicsEnabled={academicsEnabled} activeView={activeView} onNavigate={navigate} />;
                break;
            case 'fees':
                content = ['fee-items', 'invoices'].includes(activeView)
                    ? <FeeManagement {...props} expanded standalone activeView={activeView} />
                    : <FeeSetup {...props} academicsEnabled={academicsEnabled} activeSection={activeView} />;
                break;
            case 'finance-pilot':
                content = <FinancePilot branchId={branch.id} grants={financePilotGrants} />;
                break;
            case 'expenses':
                content = <ExpenseManagement key={activeView} {...props} activeView={activeView} onNavigate={navigate} />;
                break;
        }
    }

    const sectionTitle = selected?.slice(2).find(([key]) => key === activeView)?.[1];
    const moduleIcons = { academics: 'academics', students: 'users', attendance: 'attendance', guardians: 'guardians',
        inquiries: 'claims', staff: 'staff', fees: 'fees', expenses: 'expenses', 'finance-pilot': 'fees' };
    const branchNavigation = visible.length > 0 && <div className="nav-branch">
        {visible.map(([key, label, ...views]) => <NavigationGroup key={key}
            icon={moduleIcons[key]} label={label} active={key === module}>
            {views.map(([value, name]) => <button className="nav-item" type="button" key={value}
                aria-current={value === activeView ? 'page' : undefined}
                onClick={() => navigate(value)}>{name}</button>)}
        </NavigationGroup>)}
    </div>;

    return <AdminLayout screen="operations" title={sectionTitle ?? 'Branch Operations'}
        {...{ adminName, signOut, branchNavigation, platform, schoolAccess }} operations busy={signingOut}>
        <Head title={`${sectionTitle ?? 'Branch Operations'} · Schoolhouse`} />
        <div className="page-heading branch-page-heading">
            <div><h1>{sectionTitle ?? 'Branch Operations'}</h1>{branch && <p>{branch.name}</p>}</div>
            {platform && branch && activeView !== 'expense-add' && <Link className="admin-link-button" href={`/admin/branches/${branch.id}/modules`}>Branch modules</Link>}
        </div>
        {(platform || branches.length > 1) && <BranchPicker branch={branch} branches={branches} onSelect={id =>
            router.visit(id ? `/admin/branches/${id}/operations` : '/admin/operations')} />}
        {error && <p id="feedback" role="alert" data-error>{error}</p>}
        {branch && !school && !error && <p role="status">Loading branch…</p>}
        {school && !visible.length && <p>No school management modules are enabled for this branch.</p>}
        {content && <div className={branchRecords ? undefined : 'branch-workspace'} key={module}>{content}</div>}
    </AdminLayout>;
}
