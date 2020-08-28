<?php

date_default_timezone_set('UTC');

class App
{
    public $cleanable = array();
    public $protected = array();
    public $config;
    public $isLocal = false;
    public $connected = false;
    public $connectError = false;

    public function __construct()
    {
        if (!file_exists(__DIR__ . '/config.json')) {
            $data = json_encode(array(
                'protected' => array(),
                'db' => array(
                    'username' => 'root',
                    'password' => '',
                )
            ));
            file_put_contents(__DIR__ . '/config.json', $data);
        }

        $this->config = @json_decode(@file_get_contents(__DIR__ . '/config.json'));
        
        $whitelist = array(
            '127.0.0.1',
            '::1'
        );

        if (in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
            $this->isLocal = true;
        }

        $task = $this->getInput('task', 'post');
        if ($task === 'connect') {
            $username = $this->getInput('username', 'post');
            $password = $this->getInput('password', 'post');

            $this->db = @mysqli_connect('localhost', $username, $password);

            if ($this->db) {
                $this->connected = true;
                $db = array(
                    'username' => $username,
                    'password' => $password,
                );

                $this->config->db = $db;

                file_put_contents(__DIR__ . '/config.json', json_encode($this->config));
            } else {
                $this->connectError = true;
            }
        } else {
            $username = $this->config->db->username;
            $password = $this->config->db->password;
            $this->db = @mysqli_connect('localhost', $username, $password);
            
            $this->connected = !!$this->db;
        }
    }

    public function run()
    {
        if (!$this->isLocal || !$this->connected) {
            return;
        }

        $q = $this->getInput('q');

        switch ($q) {
            case 'info':
                phpinfo();
                exit;

            case 'doProtect':
                $name = $this->getInput('name');
                $this->doProtect($name);
                exit;

            case 'removeProtect':
                $name = $this->getInput('name');
                $this->removeProtect($name);
                exit;

            case 'delete':
                $name = $this->getInput('name');
                $this->delete($name);
                exit();
        }

        $result = mysqli_query($this->db, "SHOW DATABASES");
        $core = array('information_schema', 'mysql', 'performance_schema', 'sys');
        $protected = array_merge($this->config->protected, $core);

        while ($row = mysqli_fetch_array($result)) {
            if (!in_array($row[0], $protected)) {
                $this->cleanable[] = $row[0];
            }
        }
    }

    protected function delete($name)
    {
        $result = mysqli_query($this->db, "DROP DATABASE IF EXISTS `$name`");
        if ($result) {
            die(json_encode(array('error' => false)));
        } else {
            die(json_encode(array('error' => $this->db->error)));
        }
    }

    protected function removeProtect($name)
    {
        if (in_array($name, $this->config->protected)) {
            $protected = array_filter($this->config->protected, function ($item)  use ($name) {
                return $item !== $name;
            });

            $this->config->protected = array_values($protected);
            file_put_contents(__DIR__ . '/config.json', json_encode($this->config));
        }
    }

    protected function doProtect($name)
    {
        if (in_array($name, $this->config->protected)) {
            return;
        }

        $this->config->protected[] = $name;

        file_put_contents(__DIR__ . '/config.json', json_encode($this->config));
    }

    protected function getInput($name, $method = 'get')
    {
        if ($method === 'get') {
            return isset($_GET[$name]) ? $_GET[$name] : '';
        } else {
            return isset($_POST[$name]) ? $_POST[$name] : '';
        }
    }
}

$app = new App;
$app->run();
?>
<!DOCTYPE html>
<html>

<head>
    <title>localhost</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <style>
        .loading {
            position: absolute;
            top: -10px;
            bottom: -10px;
            left: -10px;
            right: -10px;
            display: none;
            justify-content: center;
            align-items: center;
            background-color: rgba(221, 221, 221, 0.3686274509803922);
            font-style: italic;
        }
    </style>
</head>

<body>
    <?php if ($app->connected) : ?>
        <div class="container">
            <h3 class="text-center">(ﾉ◕ヮ◕)ﾉ*:･ﾟ✧ (◕‿◕✿)</h3>
            <pre class="text-center"><?php print($_SERVER['SERVER_SOFTWARE']); ?></pre>
            <pre class="text-center">PHP version: <?php print phpversion(); ?> <span><a style="text-decoration: underline;" title="phpinfo()" href="/?q=info">info</a></span></pre>
            <pre class="text-center">Document Root: <?php print($_SERVER['DOCUMENT_ROOT']); ?></pre>

            <hr>
            <div class="row">
                <div class="col-md-6">
                    <h3>Folders ヾ(⌐■_■)ノ♪</h3>
                    <br>
                    <h5>♪♪♪</h5>
                    <table class="table">
                        <thead>
                            <th>#</th>
                            <th>Name</th>
                            <!-- <th></th> -->
                        </thead>
                        <tbody>
                            <?php $dirs = glob('*', GLOB_ONLYDIR); ?>
                            <?php foreach ($dirs as $key => $dir) : ?>
                                <tr>
                                    <th><?php echo $key + 1 ?></th>
                                    <td>
                                        <a title="<?php echo $dir ?>" href="<?php echo '/' . $dir ?>"><?php echo $dir ?></a>
                                    </td>
                                    <!-- <td>delete</td> -->
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <br>
                    <br>
                </div>

                <div class="col-md-6">
                    <h3>Database (~˘▾˘)~</h3>
                    <br>
                    <div class="cleanable" style="position: relative">
                        <h5>✿✿✿</h5>
                        <table class="table">
                            <thead>
                                <th><input class="checkall" type="checkbox"></th>
                                <th>Name</th>
                                <th></th>
                            </thead>
                            <tbody class="cleanable-list">
                                <?php foreach ($app->cleanable as $db) : ?>
                                    <tr class="cleanable-item item-<?php echo $db ?>">
                                        <td><input class="cleanable-checkbox" type="checkbox" value="<?php echo $db ?>"></td>
                                        <td class="db-name"><?php echo $db ?></td>
                                        <td>
                                            <a class="do-protect" href="javascript:;">protect</a>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-danger delete-btn">Delete</button>
                        <div class="loading"></div>
                    </div>
                    <br>
                    <br>
                    <br>
                    <h5>Protected Database</h5>
                    <table class="table">
                        <thead>
                            <th>Name</th>
                            <th></th>
                        </thead>
                        <tbody class="protected-list">
                            <?php
                            $list = $app->config->protected;
                            sort($list);
                            ?>
                            <?php foreach ($list as $db) : ?>
                                <tr class="protected-item">
                                    <td class="db-name"><?php echo $db ?></td>
                                    <td>
                                        <a class="do-remove-protect" href="javascript:;">remove</a>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                var $doProtect = $('.do-protect');
                var $doRemoveProtect = $('.do-remove-protect');
                var $cleanableList = $('.cleanable-list');
                var $protectedList = $('.protected-list');
                var $checkAll = $('.checkall');
                var $cleanableCheckbox = $('.cleanable-checkbox');

                function onChangeCleanableCheckbox() {
                    $checkAll.prop('checked', false);
                }

                $cleanableCheckbox.on('change', onChangeCleanableCheckbox);

                $checkAll.on('change', function() {
                    var checked = $checkAll.is(':checked');

                    $('.cleanable-checkbox').prop('checked', checked);
                });

                function onClickProtect() {
                    var $elm = $(this).parents('.cleanable-item');

                    if ($elm.data('processing')) {
                        return;
                    }

                    $elm.data('processing', 1);
                    var name = $elm.find('.db-name').text().trim();

                    $.ajax({
                            url: '/',
                            data: {
                                q: 'doProtect',
                                name: name,
                            },
                        })
                        .done(function() {
                            var html = [
                                '<tr class="protected-item">',
                                '<td class="db-name">' + name + '</td>',
                                '<td>',
                                '<a class="do-remove-protect" href="javascript:;">remove</a>',
                                '</td>',
                                '</tr>',
                            ].join('');

                            var $item = $(html);

                            $item.find('.do-remove-protect').on('click', onClickRemoveProtect);

                            $protectedList.append($item);

                            $elm.remove();
                        });
                }

                function onClickRemoveProtect() {
                    var $elm = $(this).parents('.protected-item');

                    if ($elm.data('processing')) {
                        return;
                    }

                    $elm.data('processing', 1);

                    var name = $elm.find('.db-name').text().trim();

                    $.ajax({
                            url: '/',
                            data: {
                                q: 'removeProtect',
                                name: name,
                            },
                        })
                        .done(function() {
                            var html = [
                                '<tr class="cleanable-item item-' + name + '">',
                                '<td><input class="cleanable-checkbox" type="checkbox" value="' + name + '"></td>',
                                '<td class="db-name">' + name + '</td>',
                                '<td>',
                                '<a class="do-protect" href="javascript:;">protect</a>',
                                '</td>',
                                '</tr>',
                            ].join('');

                            var $item = $(html);

                            $item.find('.do-protect').on('click', onClickProtect);
                            $item.find('.cleanable-checkbox').on('click', onChangeCleanableCheckbox);

                            $cleanableList.append($item);

                            $elm.remove();
                        });
                }

                $doProtect.on('click', onClickProtect);

                $doRemoveProtect.on('click', onClickRemoveProtect);

                var $loading = $('.loading');
                var $deleteBtn = $('.delete-btn');

                $deleteBtn.on('click', function() {
                    var list = [];

                    $('.cleanable-checkbox').each(function(idx, elm) {
                        var $elm = $(elm);

                        if ($elm.is(':checked')) {
                            list.push($elm.val());
                        }
                    });

                    runQueue(list);
                });

                function runQueue(list) {
                    if (!list.length) {
                        $loading.hide();
                        $deleteBtn.text('Delete');
                        return;
                    }

                    $loading.css('display', 'flex');
                    $deleteBtn.text('Deleting');

                    var name = list.shift();
                    var $elm = $('.item-' + name);

                    $.ajax({
                            url: '/',
                            data: {
                                q: 'delete',
                                name: name,
                            },
                            dataType: 'json',
                        })
                        .done(function(res) {
                            if (res.error) {
                                alert('Failed to delete database "' + name + '"! \nERROR: ' + res.error);
                                $loading.hide();
                                $deleteBtn.text('Delete');
                            } else {
                                $elm.remove();
                                runQueue(list);
                            }
                        });
                }
            });
        </script>
    <?php else : ?>
        <div class="container">
            <br>
            <h4>(っ＾▿＾)۶🍸🌟🍺٩(˘◡˘ ) </h4>
            <br>
            <h4>Database Connection</h4>
            <br>
            <?php if ($app->connectError): ?>
                <div class="alert alert-danger" role="alert">
                    Connect error. Please try again!
                </div>
            <?php else: ?>
                <div class="alert alert-warning" role="alert">
                    Connect database to use all features.
                </div>
            <?php endif ?>
            <form action="index.php" method="post">
                <div class="form-group row">
                    <label for="host" class="col-sm-2 col-form-label">Host</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" autocomplete="off" name="host" value="localhost" disabled required>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="username" class="col-sm-2 col-form-label">Username</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" autocomplete="off" name="username" required>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="password" class="col-sm-2 col-form-label">Password</label>
                    <div class="col-sm-10">
                        <input type="password" class="form-control" autocomplete="off" name="password">
                    </div>
                </div>
                <input type="hidden" name="task" value="connect">
                <div class="form-group row">
                    <div class="col-sm-10">
                        <button type="submit" class="btn btn-primary">Connect</button>
                    </div>
                </div>
            </form>
        </div>
    <?php endif ?>
</body>

</html>
