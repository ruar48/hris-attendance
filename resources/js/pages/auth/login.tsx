import { Form, Head } from '@inertiajs/react';
import { CalendarClock, ShieldCheck, Wallet } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

const highlights = [
    {
        icon: CalendarClock,
        title: 'Attendance & DTR',
        description: 'Biometric sync and daily time records in one place.',
    },
    {
        icon: Wallet,
        title: 'Automated payroll',
        description: 'Government-compliant computations, run in minutes.',
    },
    {
        icon: ShieldCheck,
        title: 'Secure by default',
        description: 'Role-based access keeps sensitive records protected.',
    },
];

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Log in" />

            <div className="flex min-h-svh bg-background">
                <div className="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-blue-600 p-12 text-white lg:flex">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.18),transparent_55%)]" />
                    <div className="absolute -right-24 -bottom-24 h-96 w-96 rounded-full bg-white/10 blur-3xl" />

                    <div className="relative z-10 flex items-center gap-2 text-lg font-semibold">
                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15">
                            <AppLogoIcon className="size-5 fill-current text-white" />
                        </div>
                        PayFlow
                    </div>

                    <div className="relative z-10 max-w-md">
                        <h1 className="text-3xl font-semibold text-balance">
                            Payroll and attendance, handled.
                        </h1>
                        <p className="mt-3 text-blue-100">
                            Log in to manage employees, track attendance, and
                            run payroll with confidence.
                        </p>

                        <ul className="mt-10 space-y-6">
                            {highlights.map(({ icon: Icon, title, description }) => (
                                <li key={title} className="flex items-start gap-3">
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/15">
                                        <Icon className="size-4.5" />
                                    </span>
                                    <div>
                                        <p className="font-medium">{title}</p>
                                        <p className="text-sm text-blue-100">
                                            {description}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <p className="relative z-10 text-sm text-blue-100">
                        &copy; {new Date().getFullYear()} PayFlow
                    </p>
                </div>

                <div className="flex w-full flex-col items-center justify-center px-6 py-12 lg:w-1/2">
                    <div className="w-full max-w-sm">
                        <div className="mb-8 flex flex-col items-center gap-4 text-center lg:hidden">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600">
                                <AppLogoIcon className="size-5 fill-current text-white" />
                            </div>
                            <span className="font-semibold">PayFlow</span>
                        </div>

                        <div className="mb-8">
                            <h2 className="text-2xl font-semibold">
                                Welcome back
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Enter your email and password to log in to
                                your account.
                            </p>
                        </div>

                        {status && (
                            <div className="mb-6 rounded-md bg-green-50 px-4 py-2 text-sm font-medium text-green-700 dark:bg-green-950 dark:text-green-400">
                                {status}
                            </div>
                        )}

                        <Form
                            {...store.form()}
                            resetOnSuccess={['password']}
                            className="flex flex-col gap-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-6">
                                        <div className="grid gap-2">
                                            <Label htmlFor="email">
                                                Email address
                                            </Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                name="email"
                                                required
                                                autoFocus
                                                tabIndex={1}
                                                autoComplete="email"
                                                placeholder="email@example.com"
                                            />
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <div className="flex items-center">
                                                <Label htmlFor="password">
                                                    Password
                                                </Label>
                                                {canResetPassword && (
                                                    <TextLink
                                                        href={request()}
                                                        className="ml-auto text-sm"
                                                        tabIndex={5}
                                                    >
                                                        Forgot your password?
                                                    </TextLink>
                                                )}
                                            </div>
                                            <PasswordInput
                                                id="password"
                                                name="password"
                                                required
                                                tabIndex={2}
                                                autoComplete="current-password"
                                                placeholder="Password"
                                            />
                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>

                                        <div className="flex items-center space-x-3">
                                            <Checkbox
                                                id="remember"
                                                name="remember"
                                                tabIndex={3}
                                            />
                                            <Label htmlFor="remember">
                                                Remember me
                                            </Label>
                                        </div>

                                        <Button
                                            type="submit"
                                            className="mt-2 w-full bg-blue-600 hover:bg-blue-700"
                                            tabIndex={4}
                                            disabled={processing}
                                            data-test="login-button"
                                        >
                                            {processing && <Spinner />}
                                            Log in
                                        </Button>
                                    </div>

                                    <div className="text-center text-sm text-muted-foreground">
                                        Don't have an account?{' '}
                                        <TextLink href={register()} tabIndex={5}>
                                            Sign up
                                        </TextLink>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>
                </div>
            </div>
        </>
    );
}
