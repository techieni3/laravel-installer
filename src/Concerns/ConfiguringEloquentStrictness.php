<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Concerns;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TechieNi3\LaravelInstaller\Actions\ReplaceContents;
use TechieNi3\LaravelInstaller\Replacement;

trait ConfiguringEloquentStrictness
{
    protected function configuringEloquentStrictness(mixed $directory, InputInterface $input, OutputInterface $output): void
    {
        $this->updateAppServiceProvider($directory);

        $this->updateUserModel($directory);

        $this->commitChanges('Configure Eloquent strictness.', $directory, $input, $output);
    }

    private function updateAppServiceProvider(mixed $directory): void
    {
        $replace = new ReplaceContents(file: $directory . '/app/Providers/AppServiceProvider.php');

        $replace->addReplacement($this->getNameSpaces());
        $replace->addReplacement($this->getModelStrictnessReplacements());

        $replace();
    }

    private function updateUserModel(mixed $directory): void
    {
        $userModelReplace = new ReplaceContents(file: $directory . '/app/Models/User.php');

        $userModelReplace->addReplacement($this->getUserModelStrictnessReplacements());

        $userModelReplace();
    }

    private function getNameSpaces(): Replacement
    {
        return new Replacement(
            search: 'namespace App\Providers;',
            replace: <<<'EOT'
namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

EOT,
        );
    }

    private function getModelStrictnessReplacements(): Replacement
    {
        return new Replacement(
            search: 'public function boot(): void {',
            replace: <<<'EOT'
    public function boot(): void
    {
        // Disable mass assignment protection
        Model::unguard();

        // Add strict mode if the environment is local
        Model::shouldBeStrict(App::isLocal());

        // Prohibits: db:wipe, migrate:fresh, migrate:refresh, and migrate:reset
        DB::prohibitDestructiveCommands(App::isProduction());

        // Configure the password validation rules.
        Password::defaults(fn () => App::isProduction() ? Password::min(8)->uncompromised() : null);

EOT,
        );
    }

    private function getUserModelStrictnessReplacements(): Replacement
    {
        return new Replacement(
            search: <<<'EOT'
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];
EOT,
            replace: '',
        );
    }
}
