<?php

declare(strict_types=1);

namespace Tests\Support;

use dry\orm\Model;

/**
 * A project's account-user model as {@see SyncingAccountUser}'s parent: a
 * plain dry Model whose save() counts instead of writing, so the trait's
 * `parent::save()` has an account save to run without a connection.
 *
 * @property int|null $id
 * @property string $email
 */
class UnsavedAccountModel extends Model
{
    const TABLE = 'account_user';

    /**
     * How many times the ACCOUNT was saved — the trait must not change that.
     *
     * @var int
     */
    public int $accountSaves = 0;

    /**
     * `: void`, like dry-accounts' User::save() — the parent shape that made
     * the trait's own `: void` necessary. Keeping it here pins that a
     * SyncsCustomer host under a void-typed parent composes without a fatal.
     */
    public function save(): void
    {
        $this->accountSaves++;
    }
}
