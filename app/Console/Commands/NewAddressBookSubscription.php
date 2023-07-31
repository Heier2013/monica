<?php

namespace App\Console\Commands;

use App\Domains\Contact\DavClient\Jobs\SynchronizeAddressBooks;
use App\Domains\Contact\DavClient\Services\CreateAddressBookSubscription;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class NewAddressBookSubscription extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monica:newaddressbooksubscription
                            {--email= : Monica account to add subscription to}
                            {--vaultId= : Id of the vault to add subscription to}
                            {--url= : CardDAV url of the address book}
                            {--login= : Login}
                            {--password= : Password of the account}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a new dav subscription';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (($user = $this->user()) === null) {
            return 1;
        }
        if (($vault = $this->vault()) === null) {
            return 2;
        }

        if ($user->account_id !== $vault->account_id) {
            $this->error('Vault does not belong to this account');

            return 3;
        }

        $url = $this->option('url') ?? $this->ask('CardDAV url of the address book');
        $login = $this->option('login') ?? $this->ask('Login name');
        $password = $this->option('password') ?? $this->secret('User password');

        try {
            $addressBookSubscription = app(CreateAddressBookSubscription::class)->execute([
                'account_id' => $user->account_id,
                'vault_id' => $vault->id,
                'author_id' => $user->id,
                'base_uri' => $url,
                'username' => $login,
                'password' => $password,
            ]);
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return -1;
        }

        if (! isset($addressBookSubscription)) {
            $this->error('Could not add subscription');

            return -2;
        } else {
            $this->info('Subscription added');
            SynchronizeAddressBooks::dispatch($addressBookSubscription, true);
        }

        return 0;
    }

    private function user(): ?User
    {
        if (($email = $this->option('email')) === null) {
            $this->error('Please provide an email address');

            return null;
        }

        try {
            return User::where('email', $email)->firstOrFail();
        } catch (ModelNotFoundException) {
            $this->error('Could not find user');

            return null;
        }
    }

    private function vault(): ?Vault
    {
        if (($vaultId = $this->option('vaultId')) === null) {
            $this->error('Please provide an vaultId');

            return null;
        }

        try {
            return Vault::findOrFail($vaultId);
        } catch (ModelNotFoundException) {
            $this->error('Could not find vault');

            return null;
        }
    }
}
