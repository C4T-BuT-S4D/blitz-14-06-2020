<?php

use Tarantool\Client\Client;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';

session_start();

assert_options(ASSERT_EXCEPTION, 1);

$configuration = [
    'settings' => [
        'displayErrorDetails' => true,
    ],
];

$c = new \Slim\Container($configuration);


$app = new \Slim\App($c);

$container = $app->getContainer();

$container['db'] = function ($container) {
    return Client::fromDsn("tcp://db");
};

$errorHandler = function ($request, $response, $exception) {
    return jsonError($response, $exception->getMessage());
};
$container['errorHandler'] = function ($c) use ($errorHandler) {
    return $errorHandler;
};
$container['phpErrorHandler'] = function ($c) use ($errorHandler) {
    return $errorHandler;
};

function responseJson(Response $response, $data, $status = 200)
{
    return $response->withJson($data, $status);
}

function jsonError(Response $response, $error, $status = 412)
{
    return responseJson($response, ['error' => $error], $status);
}

function parsePagination(Request $request)
{
    $params = $request->getQueryParam('paginate') ?? [];
    if (array_key_exists('limit', $params)) {
        $params['limit'] = intval($params['limit']);
    }
    if (array_key_exists('offset', $params)) {
        $params['offset'] = intval($params['offset']);
    }
    return $params;
}


$app->post('/api/register', function (Request $request, Response $response, array $args) {
    $body = $request->getParsedBody();

    /** @var Client $db */
    $db = $this->get('db');

    if (empty($body['login']) || empty($body['password'])) {
        return jsonError($response, 'Логин и пароль не переданы');
    }

    $result = $db->call('findUser', $body['login'])[0];

    if (!is_null($result)) {
        return jsonError($response, 'Пользователь уже существует');
    }

    $result = $db->call('addUser', $body['login'], $body['password']);

    $_SESSION['user'] = $result[0];


    return responseJson($response, ['user' => $result[0]]);
});

$app->post('/api/login', function (Request $request, Response $response, array $args) {
    $body = $request->getParsedBody();

    /** @var Client $db */
    $db = $this->get('db');

    if (empty($body['login']) || empty($body['password'])) {
        return jsonError($response, 'Логин и пароль не переданы');
    }

    $result = $db->call('findUser', $body['login'], $body['password'])[0];

    if (is_null($result)) {
        return jsonError($response, 'Неправильный логин или пароль');
    }

    $_SESSION['user'] = $result;

    return responseJson($response, ['user' => $result]);
});

$app->post('/api/posts', function (Request $request, Response $response, array $args) {
    assert(!empty($_SESSION['user']), 'Неавторизован');

    /** @var Client $db */
    $db = $this->get('db');

    $body = $request->getParsedBody();

    $text = $body['text'];
    assert(!empty('text'), 'Поле текст не должно быть пустым');
    $publish = $body['publish'] == 'true';


    $res = $db->call('addPost', $_SESSION['user'], $text, !$publish)[0];

    return responseJson($response, $res);
});


$app->get('/api/posts/user', function (Request $request, Response $response, array $args) {
    assert(!empty($_SESSION['user']), 'Неавторизован');
    /** @var Client $db */
    $db = $this->get('db');

    $paginator = array_merge(['limit' => 50, 'offset' => 0], parsePagination($request));
    $res = $db->call('userPosts', $_SESSION['user'], $paginator);

    return responseJson($response, ['posts' => $res[0]]);
});

$app->get('/api/posts/latest', function (Request $request, Response $response, array $args) {
    /** @var Client $db */
    $db = $this->get('db');

    $paginator = array_merge(['limit' => 50, 'offset' => 0], parsePagination($request));
    $res = $db->call('latestPosts', $paginator);

    $posts = $res[0];

    foreach ($posts as &$p) {
        $p[1] = 'Anon';
    }

    return responseJson($response, ['posts' => $posts]);
});

$app->patch('/api/posts/{post}', function (Request $request, Response $response, array $args) {
    assert(!empty($_SESSION['user']), 'Неавторизован');
    $post = intval($args['post']);

    /** @var Client $db */
    $db = $this->get('db');
    $post = $db->call('findPost', $post)[0];

    if (is_null($post)) {
        return jsonError($response, "Not found", 404);
    }

    assert($post[1] == $_SESSION['user'], "Вы не можете редактировать запись");
    assert($post[3] == true, "Вы не можете редактировать опубликованную запись");

    $body = $request->getParsedBody();

    $text = $body['text'];
    assert(!empty('text'), 'Поле текст не должно быть пустым');
    $publish = $body['publish'] == 'true';

    $post[3] = !$publish;
    $post[2] = $text;

    return responseJson($response, $db->call('updatePost', ...$post)[0]);
});

$app->get('/api/posts/{post}/token', function (Request $request, Response $response, array $args) {
    assert(!empty($_SESSION['user']), 'Неавторизован');
    $post = intval($args['post']);

    /** @var Client $db */
    $db = $this->get('db');
    $post = $db->call('findPost', $post)[0];

    if (is_null($post)) {
        return jsonError($response, "Not found", 404);
    }

    assert($post[1] == $_SESSION['user'], "Вы не можете получить токен для записи");
    assert($post[3] == true, "Вы не можете поделиться опубликованной записью");
    $token = $db->call('generateToken', $post[0]);

    return responseJson($response, ['token' => $token]);
});

$app->get('/api/posts/token', function (Request $request, Response $response, array $args) {
    $token = $request->getQueryParam('token') ?? '';

    /** @var Client $db */
    $db = $this->get('db');
    $post = $db->call('getByToken', $token)[0];

    if (is_null($post)) {
        return jsonError($response, "Not found", 404);
    }

    return responseJson($response, $post);
});


$app->run();
