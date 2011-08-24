<?php
/**
 * Bake Template for Controller action generation.
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2011, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2011, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       cake.console.libs.template.objects
 * @since         CakePHP(tm) v 1.3
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
?>

/**
 * <?php echo $admin ?>index method
 *
 * @return void
 */
	public function <?php echo $admin ?>index() {
		$this->set('<?php echo $pluralName ?>', $this->paginate());
	}

/**
 * <?php echo $admin ?>view method
 *
 * @param string $id
 * @return void
 */
	public function <?php echo $admin ?>view($id = null) {
		$<?php echo $singularName; ?> = <?php echo $currentModelName; ?>::find($id);
		if (!$<?php echo $singularName; ?>) {
			throw new NotFoundException(__('Invalid <?php echo strtolower($singularHumanName); ?>'));
		}
		$this->set('<?php echo $singularName; ?>', $<?php echo $singularName; ?>);
	}

<?php $compact = array(); ?>
/**
 * <?php echo $admin ?>add method
 *
 * @return void
 */
	public function <?php echo $admin ?>add() {
		if ($this->request->is('post')) {
			if (<?php echo $currentModelName; ?>::create($this->request->data)->save()) {
				$this-><?php echo $currentModelName; ?>->flush();
<?php if ($wannaUseSession): ?>
				$this->Session->setFlash(__('The <?php echo strtolower($singularHumanName); ?> has been saved'));
				$this->redirect(array('action' => 'index'));
<?php else: ?>
				$this->flash(__('<?php echo ucfirst(strtolower($currentModelName)); ?> saved.'), array('action' => 'index'));
<?php endif; ?>
			} else {
<?php if ($wannaUseSession): ?>
				$this->Session->setFlash(__('The <?php echo strtolower($singularHumanName); ?> could not be saved. Please, try again.'));
<?php endif; ?>
			}
		}
	}

<?php $compact = array(); ?>
/**
 * <?php echo $admin ?>edit method
 *
 * @param string $id
 * @return void
 */
	public function <?php echo $admin; ?>edit($id = null) {
		$<?php echo $singularName; ?> = <?php echo $currentModelName; ?>::find($id);
		if (!$<?php echo $singularName; ?>) {
			throw new NotFoundException(__('Invalid <?php echo strtolower($singularHumanName); ?>'));
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			if ($<?php echo $singularName; ?>->save($this->request->data)) {
				$<?php echo $singularName; ?>->flush();
<?php if ($wannaUseSession): ?>
				$this->Session->setFlash(__('The <?php echo strtolower($singularHumanName); ?> has been saved'));
				$this->redirect(array('action' => 'index'));
<?php else: ?>
				$this->flash(__('The <?php echo strtolower($singularHumanName); ?> has been saved.'), array('action' => 'index'));
<?php endif; ?>
			} else {
<?php if ($wannaUseSession): ?>
				$this->Session->setFlash(__('The <?php echo strtolower($singularHumanName); ?> could not be saved. Please, try again.'));
<?php endif; ?>
			}
		} else {
			$this->request->data = $<?php echo $singularName; ?>;
		}
	}

/**
 * <?php echo $admin ?>delete method
 *
 * @param string $id
 * @return void
 */
	public function <?php echo $admin; ?>delete($id = null) {
		if (!$this->request->is('post')) {
			throw new MethodNotAllowedException();
		}
		$<?php echo $singularName; ?> = <?php echo $currentModelName; ?>::find($id);
		if (!$<?php echo $singularName; ?>) {
			throw new NotFoundException(__('Invalid <?php echo strtolower($singularHumanName); ?>'));
		}
		if ($<?php echo $singularName; ?>->delete()) {
			$<?php echo $singularName; ?>->flush();
<?php if ($wannaUseSession): ?>
			$this->Session->setFlash(__('<?php echo ucfirst(strtolower($singularHumanName)); ?> deleted'));
			$this->redirect(array('action'=>'index'));
<?php else: ?>
			$this->flash(__('<?php echo ucfirst(strtolower($singularHumanName)); ?> deleted'), array('action' => 'index'));
<?php endif; ?>
		}
<?php if ($wannaUseSession): ?>
		$this->Session->setFlash(__('<?php echo ucfirst(strtolower($singularHumanName)); ?> was not deleted'));
<?php else: ?>
		$this->flash(__('<?php echo ucfirst(strtolower($singularHumanName)); ?> was not deleted'), array('action' => 'index'));
<?php endif; ?>
		$this->redirect(array('action' => 'index'));
	}