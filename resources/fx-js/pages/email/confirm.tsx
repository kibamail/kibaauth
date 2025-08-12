import { PageContainer, PageTitle } from "@/components/auth";
import { Button } from "@kibamail/owly";
import { Text } from "@kibamail/owly/text";

export function EmailConfirm() {
    return (
        <PageContainer>
            <div className="mb-10">
                <img src="/icons/email-send.svg" alt="Email sent" />
            </div>
            <PageTitle
                title="Verify your email address."
                description={
                    <Text
                        as="label"
                        htmlFor="code"
                        className="kb-content-tertiary"
                    >
                        We sent you a verification email to your email. Please
                        click the link to verify your email address.
                    </Text>
                }
            />

            <div className="mt-8 flex items-center gap-2">
                <Text>Didn't receive the email?</Text>
                <Button variant="tertiary" className="underline">
                    Resend verification email
                </Button>
            </div>
        </PageContainer>
    );
}
