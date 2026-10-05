<?php

test('the root redirects to the protected dashboard', function () {
    $this->get('/')->assertRedirect('/dashboard');
});
