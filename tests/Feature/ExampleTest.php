<?php

test('returns a successful response', function () {
    $response = $this->get(route('explorer.index'));

    $response->assertOk();
});
