# User Model Configuration

If you want the customer information to be saved to the user model, ensure your User model includes the necessary fields:

```php
// app/Models/User.php
class User extends Authenticatable
{
    use HasSubscriptions;

    protected $fillable = [
        'name',
        'first_name',  // Add this
        'last_name',   // Add this
        'email',
        'password',
    ];

    // ... rest of your User model
}
```

And ensure your users table migration includes these fields:

```php
// database/migrations/xxxx_xx_xx_xxxxxx_create_users_table.php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('first_name')->nullable();  // Add this
    $table->string('last_name')->nullable();   // Add this
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});
```
