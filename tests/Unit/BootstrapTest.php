<?php

it('can bootstrap Craft', function() {
    expect(Craft::$app)->not->toBeNull();
});
