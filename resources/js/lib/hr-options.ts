// Position, Employee/Employment Status, Job Level, and Shift Schedule are
// editable from the Employees > Source Data tab and served via the
// `useOptionList` hook — they are no longer hardcoded here.

export const GENDER_OPTIONS = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
];

export const MARITAL_STATUS_OPTIONS = [
    { value: 'single', label: 'Single' },
    { value: 'married', label: 'Married' },
    { value: 'widowed', label: 'Widowed' },
    { value: 'separated', label: 'Separated' },
];

export const SALARY_TYPE_OPTIONS = [
    { value: 'monthly', label: 'Monthly' },
    { value: 'daily', label: 'Daily' },
    { value: 'hourly', label: 'Hourly' },
    { value: 'semi_monthly', label: 'Semi-Monthly' },
    { value: 'weekly', label: 'Weekly' },
];

export const BANK_ACCOUNT_STATUS_OPTIONS = [
    { value: 'active', label: 'Active' },
    { value: 'pending', label: 'Pending' },
    { value: 'onhold', label: 'Onhold' },
    { value: 'closed', label: 'Closed' },
];
