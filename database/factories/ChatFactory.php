<?php

namespace Database\Factories;

use App\Models\Chat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
<<<<<<< HEAD
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat>
=======
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model=Chat>
>>>>>>> origin/distribution
 */
class ChatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Chat::class;

    public function definition()
    {
        return [
            //
        ];
    }
}
