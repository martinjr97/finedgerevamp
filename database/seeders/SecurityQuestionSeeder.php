<?php

namespace Database\Seeders;

use App\Models\SecurityQuestion;
use Illuminate\Database\Seeder;

class SecurityQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $questions = [
            'What was the name of your first school?',
            'What is your mother\'s maiden name?',
            'What was your childhood nickname?',
            'What is the name of your first pet?',
            'In what town or city were you born?',
            'What is the first name of your oldest sibling?',
            'What was the make of your first car?',
            'What is your favorite teacher\'s surname?',
            'What was the name of your childhood best friend?',
            'What is your favorite meal?',
        ];

        foreach ($questions as $index => $questionText) {
            $existing = SecurityQuestion::withTrashed()
                ->where('question', $questionText)
                ->first();

            $payload = [
                'question' => $questionText,
                'is_active' => true,
                'sort_order' => $index + 1,
            ];

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->update($payload);
                continue;
            }

            SecurityQuestion::create($payload);
        }
    }
}

