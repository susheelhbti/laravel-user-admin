<?php

namespace Susheelhbti\LaravelUserAdmin\Services;

use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;
use Susheelhbti\LaravelUserAdmin\Models\SecurityQuestion;

class SecurityQuestionService
{
    /** Return the available question bank. */
    public function questionBank(): array
    {
        return config('user_admin.security_questions.bank', [
            'What was the name of your first pet?',
            'What city were you born in?',
            'What was your childhood nickname?',
            'What is the name of your oldest sibling?',
            'What was the make of your first car?',
            'What is your mother\'s maiden name?',
            'What elementary school did you attend?',
            'What is the name of the street you grew up on?',
        ]);
    }

    /** Set questions and answers for a user. Answers are stored hashed. */
    public function set(object $user, array $questionsAndAnswers): void
    {
        // $questionsAndAnswers = [['question' => '...', 'answer' => '...'], ...]
        SecurityQuestion::where('user_id', $user->id)->delete();

        foreach ($questionsAndAnswers as $qa) {
            SecurityQuestion::create([
                'user_id'  => $user->id,
                'question' => $qa['question'],
                'answer'   => bcrypt(strtolower(trim($qa['answer']))),
            ]);
        }

        UserAdminEvents::fire(UserAdminEvents::SECURITY_QUESTION_SET, [
            'user_id' => $user->id,
            'count'   => count($questionsAndAnswers),
        ]);
    }

    /**
     * Verify answers. All provided answers must be correct.
     * Returns true/false.
     */
    public function verify(object $user, array $answers): bool
    {
        $stored = SecurityQuestion::where('user_id', $user->id)->get();

        if ($stored->isEmpty()) return false;

        $required = config('user_admin.security_questions.required', 2);
        $correct  = 0;

        foreach ($stored as $sq) {
            foreach ($answers as $answer) {
                if (password_verify(strtolower(trim($answer)), $sq->answer)) {
                    $correct++;
                    break;
                }
            }
        }

        $passed = $correct >= $required;

        UserAdminEvents::fire(
            $passed ? UserAdminEvents::SECURITY_QUESTION_PASSED : UserAdminEvents::SECURITY_QUESTION_FAILED,
            ['user_id' => $user->id]
        );

        return $passed;
    }

    public function hasSetup(object $user): bool
    {
        return SecurityQuestion::where('user_id', $user->id)->exists();
    }

    public function getQuestions(object $user): array
    {
        return SecurityQuestion::where('user_id', $user->id)
            ->pluck('question')
            ->toArray();
    }
}
