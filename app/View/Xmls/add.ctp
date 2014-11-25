<h1>Ajouter un xml</h1>
<?php
echo $this->Form->create('Xml');
echo $this->Form->input('title');
echo $this->Form->input('body', array('rows' => '3'));
echo $this->Form->end('Sauvegarder le xml');
?>