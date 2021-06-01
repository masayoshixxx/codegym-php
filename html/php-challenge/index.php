<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
    // ログインしている
    $_SESSION['time'] = time();

    $members = $db->prepare('SELECT * FROM members WHERE id=?');
    $members->execute(array($_SESSION['id']));
    $member = $members->fetch();
} else {
    // ログインしていない
    header('Location: login.php');
    exit();
}

// 投稿を記録する
if (!empty($_POST)) {
    if ($_POST['message'] != '') {
        $message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?, created=NOW()');
        $message->execute(array(
            $member['id'],
            $_POST['message'],
            $_POST['reply_post_id']
        ));

        header('Location: index.php');
        exit();
    }
}

// 返信の場合
if (isset($_REQUEST['res'])) {
    $response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
    $response->execute(array($_REQUEST['res']));

    $table = $response->fetch();
    $message = '@' . $table['name'] . ' ' . $table['message'];
}

// いいねの場合

// いいねを投稿する
if (isset($_POST['fav'])) {
    $id = $_POST['fav'];
    $fav_posts = $db->prepare('SELECT * FROM posts WHERE id=?');
    $fav_posts->execute(array($id));
    $fav_post = $fav_posts->fetch();
    $favorite = $db->prepare('INSERT INTO favorites SET member_id=?, post_id=?, created=NOW()');

    if ((int) $fav_post['retweet_post_id'] === 0) {
        $favorite->execute(array(
            $_SESSION['id'],
            $id
        ));
    } else {
        $favorite->execute(array(   
            $_SESSION['id'],
            $fav_post['retweet_post_id']
        ));
    }
    header('Location: index.php');
    exit();

// いいねを削除する
} elseif (isset($_POST['fav_not'])) {
    $fav_not_posts = $db->prepare('SELECT * FROM posts WHERE id=?');
    $fav_not_posts->execute(array($_POST['fav_not']));
    $fav_not_post = $fav_not_posts->fetch();
    $del = $db->prepare('DELETE FROM favorites WHERE member_id=? AND post_id=?');

    if ((int) $fav_not_post['retweet_post_id'] === 0) {
        $del->execute(array(
            $_SESSION['id'],
            $fav_not_post['id']
        ));
    } else {
        $del->execute(array(
            $_SESSION['id'],
            $fav_not_post['retweet_post_id']
        ));
    }
    header('Location: index.php');
    exit();
}

// リツイートの場合

// リツイートを投稿する
if (isset($_POST['rt'])) {
    $id = $_POST['rt'];
    $rt_posts = $db->prepare('SELECT * FROM posts WHERE id=?');
    $rt_posts->execute(array($id));
    $rt_post = $rt_posts->fetch();
    $retweet = $db->prepare('INSERT INTO posts SET member_id=?, message=?, retweet_post_id=?, created=NOW()');
    
    if((int) $retweet_post['retweet_post_id'] === 0) {
        $retweet->execute(array(
            $_SESSION['id'],
            $rt_post['message'],
            $id
        ));
    } else {
        $retweet->execute(array(
            $_SESSION['id'],
            $rt_post['message'],
            $rt_post['retweet_post_id']
        ));
    }
    header('Location: index.php');
    exit();

// リツイートを削除する
} elseif (isset($_POST['rt_not'])) {
    $rt_not_posts = $db->prepare('SELECT * FROM posts WHERE id=?');
    $rt_not_posts->execute(array($_POST['rt_not']));
    $rt_not_post = $rt_not_posts->fetch();

    $del = $db->prepare('DELETE FROM posts WHERE member_id=? AND retweet_post_id=?');

    if((int) $rt_not_post['retweet_post_id'] === 0) {
        $del->execute(array(
            $_SESSION['id'],
            $rt_not_post['id']
        ));
    } else {
        $del->execute(array(
            $_SESSION['id'],
            $rt_not_post['retweet_post_id']
        ));
    }
    header('Location: index.php');
    exit();
}


// 投稿を取得する
$page = $_REQUEST['page'];
if ($page == '') {
    $page = 1;
}
$page = max($page, 1);

// 最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
$cnt = $counts->fetch();
$maxPage = ceil($cnt['cnt'] / 5);
$page = min($page, $maxPage);

$start = ($page - 1) * 5;
$start = max(0, $start);

$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();


// htmlspecialcharsのショートカット
function h($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// 本文内のURLにリンクを設定します
function makeLink($value)
{
    return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<a href="\1\2">\1\2</a>', $value);
}
?>


<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>ひとこと掲示板</title>

    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <div id="wrap">
        <div id="head">
            <h1>ひとこと掲示板</h1>
        </div>
        <div id="content">
            <div style="text-align: right"><a href="logout.php">ログアウト</a></div>
            <form action="" method="post">
                <dl>
                    <dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ</dt>
                    <dd>
                        <textarea name="message" cols="50" rows="5"><?php echo h($message); ?></textarea>
                        <input type="hidden" name="reply_post_id" value="<?php echo h($_REQUEST['res']); ?>" />
                    </dd>
                </dl>
                <div>
                    <p>
                        <input type="submit" value="投稿する" />
                    </p>
                </div>
            </form>

            <?php foreach ($posts as $i => $post) : ?>

            <?php
            // よく使う分岐の簡略化
            $targetId = (int)$post['retweet_post_id'] === 0 ? $post['id'] : $post['retweet_post_id'] ;
            
            // リツイート先の情報を書き換える
            $rt_changes = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=?');
            $rt_changes->execute(array($post['retweet_post_id']));
            $rt_change = $rt_changes->fetch();


            // いいねの情報を取得する
            $fav_id = $db->prepare('SELECT * FROM favorites WHERE member_id=? AND post_id=?');
            $fav_id->execute(array($_SESSION['id'], $targetId));
            $favorite = $fav_id->fetch();

            // いいねの数を記録する
            $favcounts = $db->prepare('SELECT COUNT(post_id) as cnt FROM favorites WHERE post_id=?');
            $favcounts->execute(array($targetId));
            $favcount = $favcounts->fetch();

            // リツイートの情報を取得する
            $rt_id = $db->prepare('SELECT retweet_post_id FROM posts WHERE member_id=? AND retweet_post_id=?');
            $rt_id->execute(array($_SESSION['id'], $targetId));
            $retweet_id = $rt_id->fetch();

            // リツイートの数を記録する
            $retweetcounts = $db->prepare('SELECT COUNT(retweet_post_id) AS cnt FROM posts WHERE retweet_post_id=?');
            $retweetcounts->execute(array($targetId));
            $retweetcount = $retweetcounts->fetch();
            ?>

                <div class="msg">
                　　 <?php if ((int)$post['retweet_post_id'] === 0) : ?>  
                        <img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
                        <p><?php echo makeLink(h($post['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>             　　　　
                    <?php else : ?>
                        <?php echo $post['name'] . 'さんがリツイートしました。' . '<br>'; ?>
                        <img src="member_picture/<?php echo h($rt_change['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
                        <p><?php echo makeLink(h($rt_change['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>
                    <?php endif; ?>

                    [<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]</p>

                    <p class="day">
                        <!-- 課題：リツイートといいね機能の実装 -->

                        <!-- リツイート機能 -->
                        <form class="retweet" action="index.php" name="form_rt" method="post">

                        <?php if ((int)$retweet_id['retweet_post_id'] !== 0) : ?>
                            <input type="hidden" name="rt_not" value="<?php echo h($post['id']); ?>">
                            <a href="javascript:form_rt[<?php echo $i; ?>].submit()">                                                       
                                <img class="retweet-image" src="images/retweet-solid-blue.svg"><span style="color:blue;"><?php echo h($retweetcount['cnt']) ?></span>
                            </a>
                        <?php else : ?>
                            <input type="hidden" name="rt" value="<?php echo h($post['id']); ?>">
                            <a href="javascript:form_rt[<?php echo $i; ?>].submit()">                        
                                <img class="retweet-image" src="images/retweet-solid-gray.svg"><span style="color:gray;"><?php echo h($retweetcount['cnt']); ?></span>
                            </a>
                        <?php endif; ?>
                        </form>

                        <!-- いいね機能 -->
                        <form class="favorite" action="index.php" name="form_fav" method="post">
                        <?php if ((int)$favorite['post_id'] !== 0) : ?>
                            <input type="hidden" name="fav_not" value="<?php echo h($post['id']); ?>">
                            <a href="javascript:form_fav[<?php echo $i; ?>].submit()">                                                       
                                <img class="favorite-image" src="images/heart-solid-red.svg"><span style="color:red;"><?php echo h($favcount['cnt']); ?></span>
                            </a>
                        <?php else : ?>
                            <input type="hidden" name="fav" value="<?php echo h($post['id']); ?>">
                            <a href="javascript:form_fav[<?php echo $i; ?>].submit()">                        
                                <img class="favorite-image" src="images/heart-solid-gray.svg"><span style="color:gray;"><?php echo h($favcount['cnt']); ?></span>
                            </a>
                        <?php endif; ?>
                        </form>

                        <a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a>
                        <?php
                        if ($post['reply_post_id'] > 0) :
                        ?><a href="view.php?id=<?php echo h($post['reply_post_id']); ?>">
                                返信元のメッセージ</a>
                        <?php
                        endif;
                        ?>
                        <?php
                        if ($_SESSION['id'] == $post['member_id']) :
                        ?>
                            [<a href="delete.php?id=<?php echo h($post['id']); ?>" style="color: #F33;">削除</a>]
                        <?php
                        endif;
                        ?>
                    </p>
                </div>
            <?php endforeach; ?>

            <ul class="paging">
                <?php
                if ($page > 1) {
                ?>
                    <li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
                <?php
                } else {
                ?>
                    <li>前のページへ</li>
                <?php
                }
                ?>
                <?php
                if ($page < $maxPage) {
                ?>
                    <li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
                <?php
                } else {
                ?>
                    <li>次のページへ</li>
                <?php
                }
                ?>
            </ul>
        </div>
    </div>
</body>

</html>
