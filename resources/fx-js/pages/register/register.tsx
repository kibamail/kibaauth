import {
    AuthMethodsDivider,
    Oauth2Methods,
    PageContainer,
    PageTitle,
} from "@/components/auth";
import Axios, { AxiosError } from "axios";
import { PasswordField } from "@/components/password-field";
// import { FlashMessage } from "#root/pages/components/flash/flash_message.jsx";
import { Link, useLocation } from "wouter";
import { Button } from "@kibamail/owly/button";
import { Text } from "@kibamail/owly/text";
import * as TextField from "@kibamail/owly/text-field";
import { useMutation } from "@tanstack/react-query";
import { FormEvent } from "react";
import { formErrorsFromResponse } from "@/utils/form-errors";

interface RegisterPageProps {}

export function Register({}: RegisterPageProps) {
    const [, navigate] = useLocation();

    const registerMutation = useMutation<
        unknown,
        AxiosError,
        { email: string; password: string }
    >({
        async mutationFn(formData) {
            await Axios.post("/api/register", formData);
        },
        onSuccess() {
            navigate("/auth/register/email/confirm/");
        },
    });

    function onSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const formData = new FormData(event.currentTarget);

        const email = formData.get("email") as string;
        const password = formData.get("password") as string;

        registerMutation.mutate({ email, password });
    }

    const errors = formErrorsFromResponse(registerMutation?.error);

    return (
        <PageContainer>
            <PageTitle
                title={"Welcome to a new world of Emailing."}
                description={
                    "Choose your preferred method to access powerful emailing tools."
                }
            />

            {/*<FlashMessage className="mt-10" />*/}

            <Oauth2Methods page="register" />

            <AuthMethodsDivider>Or signup with</AuthMethodsDivider>

            <form className="flex flex-col w-full py-4" onSubmit={onSubmit}>
                <div className="grid grid-cols-1 gap-4">
                    <TextField.Root
                        id="email"
                        name="email"
                        required
                        type="email"
                        placeholder="Enter your work email address"
                    >
                        <TextField.Label htmlFor="email">
                            Email address
                        </TextField.Label>
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
                        >
                            <TextField.Label htmlFor="password">
                                Password
                            </TextField.Label>

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
                    loading={registerMutation.isPending}
                    width="full"
                    className="mt-6"
                >
                    Sign up
                </Button>
            </form>

            <div className="flex justify-center">
                <Text>
                    Already have an account?
                    <Link
                        className="ml-2 underline kb-content-info"
                        href={"/auth/login"}
                    >
                        Login
                    </Link>
                </Text>
            </div>
        </PageContainer>
    );
}
