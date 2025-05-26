<?php

namespace RiaanZA\LaravelSubscription\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscription:install 
                            {--force : Overwrite existing files}
                            {--seed : Seed sample subscription plans}
                            {--migrate : Run migrations after installation}
                            {--npm : Install and build npm dependencies}';

    /**
     * The console command description.
     */
    protected $description = 'Install the Laravel Subscription Management package';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Installing Laravel Subscription Management Package...');
        $this->newLine();

        // Check Laravel version compatibility
        if (!$this->checkLaravelVersion()) {
            return Command::FAILURE;
        }

        // Publish configuration
        $this->publishConfiguration();

        // Publish migrations
        $this->publishMigrations();

        // Publish assets
        $this->publishAssets();

        // Add trait to User model
        $this->addTraitToUserModel();

        // Update .env file
        $this->updateEnvironmentFile();

        // Run migrations if requested
        if ($this->option('migrate')) {
            $this->runMigrations();
        }

        // Seed plans if requested
        if ($this->option('seed')) {
            $this->seedPlans();
        }

        // Install npm dependencies if requested
        if ($this->option('npm')) {
            $this->installNpmDependencies();
        }

        // Display completion message
        $this->displayCompletionMessage();

        return Command::SUCCESS;
    }

    /**
     * Check Laravel version compatibility.
     */
    protected function checkLaravelVersion(): bool
    {
        $laravelVersion = app()->version();
        $requiredVersion = '11.0';

        if (version_compare($laravelVersion, $requiredVersion, '<')) {
            $this->error("❌ Laravel {$requiredVersion} or higher is required. Current version: {$laravelVersion}");
            return false;
        }

        $this->info("✅ Laravel version {$laravelVersion} is compatible");
        return true;
    }

    /**
     * Publish configuration files.
     */
    protected function publishConfiguration(): void
    {
        $this->info('📝 Publishing configuration...');

        $configExists = File::exists(config_path('laravel-subscription.php'));
        
        if ($configExists && !$this->option('force')) {
            if (!$this->confirm('Configuration file already exists. Overwrite?')) {
                $this->warn('⚠️  Skipping configuration publishing');
                return;
            }
        }

        Artisan::call('vendor:publish', [
            '--provider' => 'RiaanZA\LaravelSubscription\LaravelSubscriptionServiceProvider',
            '--tag' => 'laravel-subscription-config',
            '--force' => $this->option('force'),
        ]);

        $this->info('✅ Configuration published successfully');
    }

    /**
     * Publish migration files.
     */
    protected function publishMigrations(): void
    {
        $this->info('🗄️  Publishing migrations...');

        Artisan::call('vendor:publish', [
            '--provider' => 'RiaanZA\LaravelSubscription\LaravelSubscriptionServiceProvider',
            '--tag' => 'laravel-subscription-migrations',
            '--force' => $this->option('force'),
        ]);

        $this->info('✅ Migrations published successfully');
    }

    /**
     * Publish asset files.
     */
    protected function publishAssets(): void
    {
        $this->info('🎨 Publishing assets...');

        $assetsExist = File::exists(resource_path('js/vendor/laravel-subscription'));
        
        if ($assetsExist && !$this->option('force')) {
            if (!$this->confirm('Assets already exist. Overwrite?')) {
                $this->warn('⚠️  Skipping asset publishing');
                return;
            }
        }

        Artisan::call('vendor:publish', [
            '--provider' => 'RiaanZA\LaravelSubscription\LaravelSubscriptionServiceProvider',
            '--tag' => 'laravel-subscription-assets',
            '--force' => $this->option('force'),
        ]);

        $this->info('✅ Assets published successfully');
    }

    /**
     * Add HasSubscriptions trait to User model.
     */
    protected function addTraitToUserModel(): void
    {
        $this->info('👤 Adding HasSubscriptions trait to User model...');

        $userModelPath = app_path('Models/User.php');
        
        if (!File::exists($userModelPath)) {
            $this->warn('⚠️  User model not found at expected location');
            return;
        }

        $userModelContent = File::get($userModelPath);
        $traitNamespace = 'RiaanZA\LaravelSubscription\Traits\HasSubscriptions';
        $traitUse = 'use HasSubscriptions;';

        // Check if trait is already added
        if (str_contains($userModelContent, $traitNamespace)) {
            $this->info('✅ HasSubscriptions trait already added to User model');
            return;
        }

        // Add use statement
        if (!str_contains($userModelContent, "use {$traitNamespace};")) {
            $pattern = '/^(use .+;)$/m';
            $replacement = "$1\nuse {$traitNamespace};";
            $userModelContent = preg_replace($pattern, $replacement, $userModelContent, 1);
        }

        // Add trait usage in class
        if (!str_contains($userModelContent, $traitUse)) {
            $pattern = '/(class User extends Authenticatable\s*{)/';
            $replacement = "$1\n    use HasSubscriptions;\n";
            $userModelContent = preg_replace($pattern, $replacement, $userModelContent);
        }

        if ($this->option('force') || $this->confirm('Add HasSubscriptions trait to User model?')) {
            File::put($userModelPath, $userModelContent);
            $this->info('✅ HasSubscriptions trait added to User model');
        } else {
            $this->warn('⚠️  Skipping User model modification');
            $this->line('   Please manually add the trait to your User model:');
            $this->line("   use {$traitNamespace};");
            $this->line("   class User extends Authenticatable {");
            $this->line("       use HasSubscriptions;");
            $this->line("   }");
        }
    }

    /**
     * Update environment file with subscription settings.
     */
    protected function updateEnvironmentFile(): void
    {
        $this->info('🔧 Updating environment configuration...');

        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            $this->warn('⚠️  .env file not found');
            return;
        }

        $envContent = File::get($envPath);
        $envVars = [
            'PEACH_WEBHOOK_URL' => '/webhooks/peach-payments',
            'PEACH_RETURN_URL' => '/subscription/payment/success',
            'PEACH_CANCEL_URL' => '/subscription/payment/cancelled',
        ];

        $newVars = [];
        foreach ($envVars as $key => $defaultValue) {
            if (!str_contains($envContent, $key)) {
                $newVars[] = "{$key}={$defaultValue}";
            }
        }

        if (!empty($newVars)) {
            $envContent .= "\n\n# Laravel Subscription Management\n" . implode("\n", $newVars) . "\n";
            File::put($envPath, $envContent);
            $this->info('✅ Environment variables added');
            
            foreach ($newVars as $var) {
                $this->line("   {$var}");
            }
        } else {
            $this->info('✅ Environment variables already configured');
        }
    }

    /**
     * Run database migrations.
     */
    protected function runMigrations(): void
    {
        $this->info('🗄️  Running migrations...');

        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->info('✅ Migrations completed successfully');
        } catch (\Exception $e) {
            $this->error('❌ Migration failed: ' . $e->getMessage());
            $this->warn('   Please run migrations manually: php artisan migrate');
        }
    }

    /**
     * Seed sample subscription plans.
     */
    protected function seedPlans(): void
    {
        $this->info('🌱 Seeding sample subscription plans...');

        try {
            Artisan::call('subscription:seed-plans');
            $this->info('✅ Sample plans seeded successfully');
        } catch (\Exception $e) {
            $this->error('❌ Seeding failed: ' . $e->getMessage());
            $this->warn('   Please run seeding manually: php artisan subscription:seed-plans');
        }
    }

    /**
     * Install npm dependencies.
     */
    protected function installNpmDependencies(): void
    {
        $this->info('📦 Installing npm dependencies...');

        $packageJsonPath = base_path('package.json');
        
        if (!File::exists($packageJsonPath)) {
            $this->warn('⚠️  package.json not found');
            return;
        }

        // Check if required dependencies are already installed
        $packageJson = json_decode(File::get($packageJsonPath), true);
        $requiredDeps = [
            '@inertiajs/vue3' => '^1.0.0',
            'vue' => '^3.3.0',
            '@tailwindcss/forms' => '^0.5.0',
        ];

        $missingDeps = [];
        foreach ($requiredDeps as $dep => $version) {
            if (!isset($packageJson['dependencies'][$dep]) && !isset($packageJson['devDependencies'][$dep])) {
                $missingDeps[$dep] = $version;
            }
        }

        if (!empty($missingDeps)) {
            $this->warn('⚠️  Missing required npm dependencies:');
            foreach ($missingDeps as $dep => $version) {
                $this->line("   {$dep}: {$version}");
            }
            
            if ($this->confirm('Install missing dependencies?')) {
                $deps = array_map(fn($dep, $version) => "{$dep}@{$version}", array_keys($missingDeps), $missingDeps);
                $this->runNpmCommand(['install', '--save'] + $deps);
            }
        }

        // Build assets
        if ($this->confirm('Build assets?')) {
            $this->runNpmCommand(['run', 'build']);
        }
    }

    /**
     * Run npm command.
     */
    protected function runNpmCommand(array $command): void
    {
        $process = new Process(['npm'] + $command, base_path());
        $process->setTimeout(300); // 5 minutes

        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $this->error('❌ npm command failed');
        }
    }

    /**
     * Display completion message.
     */
    protected function displayCompletionMessage(): void
    {
        $this->newLine();
        $this->info('🎉 Laravel Subscription Management Package installed successfully!');
        $this->newLine();

        $this->line('📋 <comment>Next Steps:</comment>');
        $this->line('   1. Configure your Peach Payments credentials in .env');
        $this->line('   2. Run migrations: <info>php artisan migrate</info>');
        $this->line('   3. Seed sample plans: <info>php artisan subscription:seed-plans</info>');
        $this->line('   4. Configure your subscription plans in the database');
        $this->line('   5. Test the subscription flow');
        $this->newLine();

        $this->line('📚 <comment>Documentation:</comment>');
        $this->line('   - Configuration: config/laravel-subscription.php');
        $this->line('   - Routes: /subscription/plans, /subscription/dashboard');
        $this->line('   - API: /api/subscription/*');
        $this->newLine();

        $this->line('🔧 <comment>Middleware Usage:</comment>');
        $this->line('   - Subscription required: <info>subscription:active</info>');
        $this->line('   - Feature required: <info>subscription:feature_name</info>');
        $this->line('   - Usage limits: <info>usage-limit:feature_name,increment</info>');
        $this->newLine();

        if (!$this->option('migrate')) {
            $this->warn('⚠️  Don\'t forget to run migrations: php artisan migrate');
        }

        if (!$this->option('seed')) {
            $this->warn('⚠️  Consider seeding sample plans: php artisan subscription:seed-plans');
        }
    }
}
