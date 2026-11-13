<?php

use Illuminate\Http\Request;

test('parent navigation links lead to the first submenu item', function () {
    $header = view('components.layouts.app.header')->render();

    expect($header)
        ->toMatch('/<a\\s+href="'.preg_quote(url('articles'), '/').'"\\s+class="flex flex-col/s')
        ->toMatch('/<a\\s+href="'.preg_quote(url('mentorship'), '/').'"\\s+class="flex flex-col/s')
        ->toContain('aria-label="Toggle Publications menu"')
        ->toContain('aria-label="Toggle Mentorship menu"');
});

test('the current submenu item uses the active color', function () {
    app()->instance('request', Request::create('/articles'));

    $header = view('components.layouts.app.header')->render();

    expect($header)
        ->toContain('Article List</span>')
        ->toContain('fa-list w-5 text-center shrink-0 text-sky-600 dark:text-sky-400');
});

test('the Q&A navigation item is active on question detail pages', function () {
    app()->instance('request', Request::create('/question/example-question'));

    $header = view('components.layouts.app.header')->render();

    expect($header)->toMatch('/href="'.preg_quote(url('questions'), '/').'"[^>]*!text-sky-600 dark:!text-sky-400 bg-sky-50/s');
});
