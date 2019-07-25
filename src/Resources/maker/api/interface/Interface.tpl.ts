export interface <?= $className ?> {
    <?php foreach($members as $member) { ?>
    <?= $member['name'] ?>: <?= $member['type'] ?>;
    <?php } ?>

}