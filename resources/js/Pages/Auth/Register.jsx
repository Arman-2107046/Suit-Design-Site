import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });
    const [showPassword, setShowPassword] = useState(false);

    const submit = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout
            title="Create your account"
            subtitle="Save designs, reorder in a click and keep your measurements on file."
            aside={
                <>
                    Already have an account?{' '}
                    <Link href={route('login')} className="font-medium text-gray-900 underline-offset-4 hover:underline">
                        Sign in
                    </Link>
                </>
            }
        >
            <Head title="Create account" />

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel htmlFor="name" value="Full name" />
                    <TextInput
                        id="name"
                        name="name"
                        value={data.name}
                        className="mt-1.5 block w-full"
                        autoComplete="name"
                        placeholder="Alex Morgan"
                        isFocused={true}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />
                    <InputError message={errors.name} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1.5 block w-full"
                        autoComplete="username"
                        placeholder="you@example.com"
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="password" value="Password" />
                        <div className="relative mt-1.5">
                            <TextInput
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                name="password"
                                value={data.password}
                                className="block w-full pr-12"
                                autoComplete="new-password"
                                placeholder="8+ characters"
                                onChange={(e) => setData('password', e.target.value)}
                                required
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword((v) => !v)}
                                aria-label={showPassword ? 'Hide password' : 'Show password'}
                                className="absolute inset-y-0 right-0 flex items-center px-4 text-gray-400 transition-colors hover:text-gray-900"
                            >
                                {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                            </button>
                        </div>
                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="password_confirmation" value="Confirm password" />
                        <TextInput
                            id="password_confirmation"
                            type={showPassword ? 'text' : 'password'}
                            name="password_confirmation"
                            value={data.password_confirmation}
                            className="mt-1.5 block w-full"
                            autoComplete="new-password"
                            placeholder="Repeat it"
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            required
                        />
                        <InputError message={errors.password_confirmation} className="mt-2" />
                    </div>
                </div>

                <PrimaryButton className="w-full gap-2 py-3.5" disabled={processing}>
                    {processing ? 'Creating account…' : 'Create account'}
                    {!processing && <ArrowRight className="h-4 w-4" />}
                </PrimaryButton>

                <p className="text-xs leading-relaxed text-gray-400">
                    By creating an account you agree to our{' '}
                    <a href="#" className="text-gray-600 underline-offset-2 hover:underline">Terms</a> and{' '}
                    <a href="#" className="text-gray-600 underline-offset-2 hover:underline">Privacy Policy</a>.
                </p>
            </form>
        </GuestLayout>
    );
}
