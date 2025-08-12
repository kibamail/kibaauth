import {
    AuthMethodsDivider,
    Oauth2Methods,
    PageContainer,
    PageTitle,
} from "@/components/auth.jsx";
// import { FlashMessage } from '#root/pages/components/flash/flash_message.jsx'
import { PasswordField } from "@/components/password-field";
import { Button } from "@kibamail/owly/button";
import { Text } from "@kibamail/owly/text";
import * as TextField from "@kibamail/owly/text-field";
import React from "react";
import { Link } from "wouter";

interface LoginPageProps {}

export function Login({}: LoginPageProps) {
    return (
        <PageContainer>
            <PageTitle
                title={"Welcome to a new world of Emailing."}
                description={
                    "Choose your preferred method to access powerful emailing tools."
                }
            />

            <Oauth2Methods page="login" />

            <AuthMethodsDivider>Or continue with</AuthMethodsDivider>

            <div className="flex flex-col w-full py-4">
                <div className="grid grid-cols-1 gap-4">
                    <TextField.Root
                        id="email"
                        placeholder="Enter your work email address"
                        name="email"
                    >
                        <TextField.Label htmlFor="email">
                            Email address
                        </TextField.Label>

                        {/*{error?.errorsMap?.email ? (
              <TextField.Error>{error?.errorsMap?.email}</TextField.Error>
            ) : null}*/}
                    </TextField.Root>

                    <PasswordField name="password">
                        {/*{error?.errorsMap?.password ? (
              <TextField.Error>{error?.errorsMap?.password}</TextField.Error>
            ) : null}*/}
                    </PasswordField>
                </div>

                <div className="flex justify-end">
                    <Button asChild variant="tertiary" className="underline">
                        <Link href={"/auth/passwords/forgot"}>
                            Forgot your password ?
                        </Link>
                    </Button>
                </div>

                <Button type="submit" width="full" className="mt-2">
                    Continue
                </Button>
            </div>

            <div className="flex justify-center">
                <Text>
                    Don{"'"}t have an account?
                    <Link
                        className="ml-2 underline kb-content-info"
                        href={"/auth/register"}
                    >
                        Create an account
                    </Link>
                </Text>
            </div>
        </PageContainer>
    );
}
