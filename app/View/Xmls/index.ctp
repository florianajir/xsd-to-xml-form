<h1>Xml created</h1>
<?php echo $this->Html->link(
    'Ajouter un Xml',
    array('action' => 'add')
); ?>
<table>
    <tr>
        <th>Id</th>
        <th>Titre</th>
        <th>Action</th>
        <th>Créé le</th>
    </tr>

    <!-- Here is where we loop through our $posts array, printing out post info -->

    <?php foreach ($xmls as $xml): ?>
        <tr>
            <td><?php echo $xml['Xml']['id']; ?></td>
            <td>
                <?php echo $this->Html->link($xml['Xml']['title'],
                    array('action' => 'view', $xml['Xml']['id'])); ?>
            </td>
            <td>
                <?php echo $this->Form->postLink(
                    'Supprimer',
                    array('action' => 'delete', $xml['Xml']['id']),
                    array('confirm' => 'Etes-vous sûr ?'));
                ?>
                <?php echo $this->Html->link(
                    'Editer',
                    array('action' => 'edit', $xml['Xml']['id'])
                ); ?>
            </td>
            <td><?php echo $xml['Xml']['created']; ?></td>
        </tr>
    <?php endforeach; ?>
    <?php unset($xml); ?>
</table>