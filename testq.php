<?php


use FpDbTest\Database;

spl_autoload_register(function ($class) {
    $a = array_slice(explode('\\', $class), 1);
    if (!$a) {
        throw new Exception();
    }
    $filename = implode('/', [__DIR__, ...$a]) . '.php';
    require_once $filename;
});

$dbq = new Database(null);

$results = [];

$results[] = $dbq->buildQuery("SELECT name FROM users WHERE user_id = 1");

$results[] = $dbq->buildQuery(
    'SELECT * FROM users WHERE name = ? AND block = 0',
    ['Jack']
);

$results[] = $dbq->buildQuery(
    'SELECT ?# FROM users WHERE user_id = ?d AND block = ?d',
    [['name', 'email'], 2, true]
);

$results[] = $dbq->buildQuery(
    'UPDATE users SET ?a WHERE user_id = -1',
    [['name' => 'Jack', 'email' => null]]
);

foreach ([null, true] as $block) {
    $results[] = $dbq->buildQuery(
        'SELECT name FROM users WHERE ?# IN (?a){ AND block = ?d}',
        ['user_id', [1, 2, 3], $block ?? $dbq->skip()]
    );

    echo "======\n";
}


// SQL инъекция
// $dbq->buildQuery(
//     'SELECT * FROM users WHERE login = ?',
//     ['\' OR 1=1--']
// );

$correct = [
    'SELECT name FROM users WHERE user_id = 1',
    'SELECT * FROM users WHERE name = \'Jack\' AND block = 0',
    'SELECT `name`, `email` FROM users WHERE user_id = 2 AND block = 1',
    'UPDATE users SET `name` = \'Jack\', `email` = NULL WHERE user_id = -1',
    'SELECT name FROM users WHERE `user_id` IN (1, 2, 3)',
    'SELECT name FROM users WHERE `user_id` IN (1, 2, 3) AND block = 1',
];

print_r($results);

print_r($correct);


if ($results !== $correct) {
    echo "wrong\n";
    // throw new Exception('Failure.');
}
