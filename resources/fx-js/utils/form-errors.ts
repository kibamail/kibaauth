import { AxiosError } from "axios";

export function formErrorsFromResponse(error: AxiosError | null) {
    const errorsMap: Record<string, string> = {};

    const errors = error?.response?.data as {
        errors: Record<string, string[]>;
    };

    if (error?.status !== 422) {
        return {
            "500": "An error occurred processing your request. Please try again.",
        };
    }

    if (!errors) return errorsMap;

    for (const [key, value] of Object.entries(errors.errors)) {
        errorsMap[key] = value[0];
    }

    return errorsMap;
}
