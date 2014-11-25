<h1>Editer le xml</h1>
<?php
echo $this->Form->create('Xml');
echo $this->Form->input('title');
echo $this->Form->input('content', array('rows' => '3'));
echo $this->Form->input('id', array('type' => 'hidden'));
echo $this->Form->end('Save Xml');
?>