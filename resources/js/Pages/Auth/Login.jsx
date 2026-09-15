import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';

export default function Login({ status, canResetPassword, canRegister = true }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });
    const [showPassword, setShowPassword] = useState(false);

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout
            title="Welcome back"
            subtitle="Sign in to pick up your designs where you left off."
            aside={
                canRegister && (
                    <>
                        New here?{' '}
                        <Link href={route('register')} className="font-medium text-gray-900 underline-offset-4 hover:underline">
                            Create an account
                        </Link>
                    </>
                )
            }
        >
            <Head title="Sign in" />

            {status && (
                <div className="mb-6 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
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
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <div className="flex items-baseline justify-between">
                        <InputLabel htmlFor="password" value="Password" />
                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="text-[13px] text-gray-500 transition-colors hover:text-gray-900"
                            >
                                Forgot password?
                            </Link>
                        )}
                    </div>
                    <div className="relative mt-1.5">
                        <TextInput
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            name="password"
                            value={data.password}
                            className="block w-full pr-12"
                            autoComplete="current-password"
                            placeholder="••••••••"
                            onChange={(e) => setData('password', e.target.value)}
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

                <label className="flex items-center gap-2.5">
                    <Checkbox name="remember" checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} />
                    <span className="text-sm text-gray-600">Keep me signed in</span>
                </label>

                <PrimaryButton className="w-full gap-2 py-3.5" disabled={processing}>
                    {processing ? 'Signing in…' : 'Sign in'}
                    {!processing && <ArrowRight className="h-4 w-4" />}
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
