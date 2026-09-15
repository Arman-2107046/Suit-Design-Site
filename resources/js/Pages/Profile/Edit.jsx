import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

const Panel = ({ children, className = '' }) => (
    <div className={`rounded-3xl bg-white p-7 shadow-[0_1px_0_rgba(0,0,0,0.04),0_20px_50px_-30px_rgba(0,0,0,0.15)] sm:p-9 ${className}`}>{children}</div>
);

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <AuthenticatedLayout eyebrow="Your atelier" title="Account settings" subtitle="Your details, your password, and the option to close the account.">
            <Head title="Account settings" />

            <div className="grid gap-6 lg:grid-cols-12">
                <Panel className="lg:col-span-7 animate-slide-up">
                    <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} className="max-w-xl" />
                </Panel>

                <div className="grid gap-6 lg:col-span-5 animate-slide-up [animation-delay:80ms]">
                    <Panel>
                        <UpdatePasswordForm />
                    </Panel>
                    <Panel className="border border-red-100">
                        <DeleteUserForm />
                    </Panel>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
