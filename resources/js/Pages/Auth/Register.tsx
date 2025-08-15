// import InputError from '@/Components/InputError';
// import InputLabel from '@/Components/InputLabel';
// import PrimaryButton from '@/Components/PrimaryButton';
// import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

// export default function Register() {
//     const { data, setData, post, processing, errors, reset } = useForm({
//         name: '',
//         email: '',
//         password: '',
//         password_confirmation: '',
//     });

//     const submit: FormEventHandler = (e) => {
//         e.preventDefault();

//         post(route('register'), {
//             onFinish: () => reset('password', 'password_confirmation'),
//         });
//     };

//     return (
//         <GuestLayout>
//             <Head title="Register" />

//             <form onSubmit={submit}>
//                 <div>
//                     <InputLabel htmlFor="name" value="Name" />

//                     <TextInput
//                         id="name"
//                         name="name"
//                         value={data.name}
//                         className="mt-1 block w-full"
//                         autoComplete="name"
//                         isFocused={true}
//                         onChange={(e) => setData('name', e.target.value)}
//                         required
//                     />

//                     <InputError message={errors.name} className="mt-2" />
//                 </div>

//                 <div className="mt-4">
//                     <InputLabel htmlFor="email" value="Email" />

//                     <TextInput
//                         id="email"
//                         type="email"
//                         name="email"
//                         value={data.email}
//                         className="mt-1 block w-full"
//                         autoComplete="username"
//                         onChange={(e) => setData('email', e.target.value)}
//                         required
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
//                         autoComplete="new-password"
//                         onChange={(e) => setData('password', e.target.value)}
//                         required
//                     />

//                     <InputError message={errors.password} className="mt-2" />
//                 </div>

//                 <div className="mt-4">
//                     <InputLabel
//                         htmlFor="password_confirmation"
//                         value="Confirm Password"
//                     />

//                     <TextInput
//                         id="password_confirmation"
//                         type="password"
//                         name="password_confirmation"
//                         value={data.password_confirmation}
//                         className="mt-1 block w-full"
//                         autoComplete="new-password"
//                         onChange={(e) =>
//                             setData('password_confirmation', e.target.value)
//                         }
//                         required
//                     />

//                     <InputError
//                         message={errors.password_confirmation}
//                         className="mt-2"
//                     />
//                 </div>

//                 <div className="mt-4 flex items-center justify-end">
//                     <Link
//                         href={route('login')}
//                         className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
//                     >
//                         Already registered?
//                     </Link>

//                     <PrimaryButton className="ms-4" disabled={processing}>
//                         Register
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
} from '@/Components/auth';
import { PasswordField } from '@/Components/password-field';
// import { FlashMessage } from "#root/pages/components/flash/flash_message.jsx";
import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@kibamail/owly/button';
import { Text } from '@kibamail/owly/text';
import * as TextField from '@kibamail/owly/text-field';

interface RegisterPageProps {}

export default function Register({}: RegisterPageProps) {
  const { data, setData, post, processing, errors, reset } = useForm({
    email: '',
    password: '',
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();

    post(route('register'), {
      onFinish: () => reset('password'),
    });
  };

  return (
    <GuestLayout>
      <Head title="Register" />
      <PageContainer>
        <PageTitle
          title={'Welcome to a new world of Emailing.'}
          description={
            'Choose your preferred method to access powerful emailing tools.'
          }
        />

        {/*<FlashMessage className="mt-10" />*/}

        <Oauth2Methods page="register" />

        <AuthMethodsDivider>Or signup with</AuthMethodsDivider>

        <form className="flex w-full flex-col py-4" onSubmit={submit}>
          <div className="grid grid-cols-1 gap-4">
            <TextField.Root
              id="email"
              name="email"
              required
              type="email"
              placeholder="Enter your work email address"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
            >
              <TextField.Label htmlFor="email">Email address</TextField.Label>
              {errors?.email ? (
                <TextField.Error>{errors?.email}</TextField.Error>
              ) : null}
            </TextField.Root>

            <div className="relative">
              <PasswordField
                required
                strengthIndicator
                name="password"
                id="new-password"
                placeholder="Choose a password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
              >
                <TextField.Label htmlFor="password">Password</TextField.Label>

                {errors?.password ? (
                  <TextField.Error className="mt-6">
                    {errors?.password}
                  </TextField.Error>
                ) : null}
              </PasswordField>
            </div>
          </div>

          <Button
            type="submit"
            loading={processing}
            width="full"
            className="mt-6"
          >
            Sign up
          </Button>
        </form>

        <div className="flex justify-center">
          <Text>
            Already have an account?
            <Link className="kb-content-info ml-2 underline" href={'/login'}>
              Login
            </Link>
          </Text>
        </div>
      </PageContainer>
    </GuestLayout>
  );
}
