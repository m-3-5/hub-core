<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class WelcomeController extends Controller
{
    public function __invoke(): View
    {
        $slides = collect(config('welcome.slides'))->map(function (array $slide) {
            $slide['image_url'] = asset($slide['image']);
            $slide['cta_url'] = match ($slide['href']) {
                'login' => route('admin.login'),
                'register' => route('welcome').'#registrazione',
                default => route('admin.login'),
            };

            return $slide;
        });

        return view('welcome', [
            'slides' => $slides,
            'loopSlides' => $slides->concat($slides),
        ]);
    }
}
