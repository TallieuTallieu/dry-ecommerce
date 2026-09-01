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
     * @return void
     */
    public function save()
    {
        $this->accountSaves++;
    }
}
