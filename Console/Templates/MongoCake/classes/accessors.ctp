<?php foreach ($properties as $p => $methods) :
    foreach ($methods as $m) :
        $isGetter = $m[0] === 'g';
        $type = empty($schema[$p]['targetDocument']) ? $schema[$p]['type'] : $schema[$p]['targetDocument'];
        if ($schema[$p]['type'] === 'many') {
            $type = "array<$type>";
        }
?>
<?php if ($isGetter) : ?>
/**
 * Returns the <?php echo $p ?> property
 *
 * @return <?php echo $type . PHP_EOL; ?>
 */
	public function <?php echo $m ?>() {
		return $this-><?php echo $p ?>;
	}

<?php else :?>
/**
 * Sets the value for the <?php echo $p ?> property
 *
 * @param <?php echo $type?> $value
 * @return void
 */
	public function <?php echo $m ?>($value) {
		$this-><?php echo $p ?> = $value;
	}

<?php endif; ?>
<?php endforeach; ?>
<?php endforeach; ?>