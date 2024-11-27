<?php

namespace UtanvetEllenor;

class Reasons
{
    public const TEST_HASH = 'Test hash was used.';
    public const OUT_OF_QUOTA = 'Run out of request quota for current billing period, upgrade your subscription to resolve!';
    public const EXCEPTION_FOUND = 'Active exception found for this hash in your account.';
    public const TEMP_EMAIL = 'Temporary e-mail was used.';
    public const MAILBOX_NON_EXISTENT = 'Mailbox does not exist.';
    public const NOT_FOUND = 'No Signals were found.';
    public const THRESHOLD_NOT_MET = 'Total rate did not meet the minimum threshold set.';
    public const PASSED = 'Signals found, checks passed.';
}
