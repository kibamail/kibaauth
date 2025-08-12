// import Checkbox from '@/Components/Checkbox';
// import InputError from '@/Components/InputError';
// import InputLabel from '@/Components/InputLabel';
// import PrimaryButton from '@/Components/PrimaryButton';
// import TextInput from '@/Components/TextInput';
// import GuestLayout from '@/Layouts/GuestLayout';
// import { Head, Link, useForm } from '@inertiajs/react';
// import { FormEventHandler } from 'react';

// export default function Login({
//     status,
//     canResetPassword,
// }: {
//     status?: string;
//     canResetPassword: boolean;
// }) {
// const { data, setData, post, processing, errors, reset } = useForm({
//     email: '',
//     password: '',
//     remember: false as boolean,
// });

// const submit: FormEventHandler = (e) => {
//     e.preventDefault();

//     post(route('login'), {
//         onFinish: () => reset('password'),
//     });
// };

//     return (
//         <GuestLayout>
//             <Head title="Log in" />

//             {status && (
//                 <div className="mb-4 text-sm font-medium text-green-600">
//                     {status}
//                 </div>
//             )}

//             <form onSubmit={submit}>
//                 <div>
//                     <InputLabel htmlFor="email" value="Email" />

//                     <TextInput
//                         id="email"
//                         type="email"
//                         name="email"
//                         value={data.email}
//                         className="mt-1 block w-full"
//                         autoComplete="username"
//                         isFocused={true}
//                         onChange={(e) => setData('email', e.target.value)}
//                     />

//                     <InputError message={errors.email} className="mt-2" />
//                 </div>

//                 <div className="mt-4">
//                     <InputLabel htmlFor="password" value="Password" />

//                     <TextInput
//                         id="password"
//                         type="password"
//                         name="password"
//                         value={data.password}
//                         className="mt-1 block w-full"
//                         autoComplete="current-password"
//                         onChange={(e) => setData('password', e.target.value)}
//                     />

//                     <InputError message={errors.password} className="mt-2" />
//                 </div>

//                 <div className="mt-4 block">
//                     <label className="flex items-center">
//                         <Checkbox
//                             name="remember"
//                             checked={data.remember}
//                             onChange={(e) =>
//                                 setData(
//                                     'remember',
//                                     (e.target.checked || false) as false,
//                                 )
//                             }
//                         />
//                         <span className="ms-2 text-sm text-gray-600 dark:text-gray-400">
//                             Remember me
//                         </span>
//                     </label>
//                 </div>

//                 <div className="mt-4 flex items-center justify-end">
//                     {canResetPassword && (
//                         <Link
//                             href={route('password.request')}
//                             className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
//                         >
//                             Forgot your password?
//                         </Link>
//                     )}

//                     <PrimaryButton className="ms-4" disabled={processing}>
//                         Log in
//                     </PrimaryButton>
//                 </div>
//             </form>
//         </GuestLayout>
//     );
// }

import {
  AuthMethodsDivider,
  Oauth2Methods,
  PageContainer,
  PageTitle,
} from '@/Components/auth.jsx';
// import { FlashMessage } from '#root/pages/components/flash/flash_message.jsx'
import { PasswordField } from '@/Components/password-field';
import { CheckCircleSolidIcon } from '@/icons/check-circle-solid.svg';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import * as Alert from '@kibamail/owly/alert';
import { Button } from '@kibamail/owly/button';
import { Text } from '@kibamail/owly/text';
import * as TextField from '@kibamail/owly/text-field';
import { FormEventHandler } from 'react';
interface LoginPageProps {
  passwordResetSuccess?: string;
}

export default function Login({ passwordResetSuccess }: LoginPageProps) {
  const { data, setData, post, processing, errors, reset } = useForm({
    email: '',
    password: '',
    remember: true as boolean,
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();

    post(route('login'), {
      onFinish: () => reset('password'),
    });
  };

  return (
    <GuestLayout>
      <Head title="Login" />
      <PageContainer>
        <PageTitle
          title={'Welcome to a new world of Emailing.'}
          description={
            'Choose your preferred method to access powerful emailing tools.'
          }
        />

        {passwordResetSuccess ? (
          <Alert.Root className="mt-4 -mb-6" variant={'success'}>
            <Alert.Icon>
              <CheckCircleSolidIcon />
            </Alert.Icon>

            <div className="flex w-full flex-col">
              <Alert.Title className="font-medium">
                Password reset successful
              </Alert.Title>

              <Text className="kb-content-secondary mt-1">
                Your password has been reset successfully. You can now login
                with your new password.
              </Text>
            </div>
          </Alert.Root>
        ) : null}

        <Oauth2Methods page="login" />

        <AuthMethodsDivider>Or continue with</AuthMethodsDivider>

        <form onSubmit={submit} className="flex w-full flex-col py-4">
          <div className="grid grid-cols-1 gap-4">
            <TextField.Root
              id="email"
              placeholder="Enter your work email address"
              name="email"
              onChange={(e) => setData('email', e.target.value)}
              value={data.email}
            >
              <TextField.Label htmlFor="email">Email address</TextField.Label>

              {errors?.email ? (
                <TextField.Error>{errors?.email}</TextField.Error>
              ) : null}
            </TextField.Root>

            <PasswordField
              name="password"
              onChange={(e) => setData('password', e.target.value)}
              value={data.password}
            >
              {errors?.password ? (
                <TextField.Error>{errors?.password}</TextField.Error>
              ) : null}
            </PasswordField>
          </div>

          <div className="flex justify-end">
            <Button asChild variant="tertiary" className="underline">
              <Link href={route('password.request')}>
                Forgot your password ?
              </Link>
            </Button>
          </div>

          <Button
            type="submit"
            width="full"
            className="mt-2"
            loading={processing}
          >
            Continue
          </Button>
        </form>

        <div className="flex justify-center">
          <Text>
            Don{"'"}t have an account?
            <Link
              className="kb-content-info ml-2 underline"
              href={route('register')}
            >
              Create an account
            </Link>
          </Text>
        </div>
      </PageContainer>
    </GuestLayout>
  );
}
