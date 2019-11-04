<?php

class DAV_OPTIONS extends DAV
{

	/**
	 * HTTP-Methode OPTIONS.<br>
	 * <br>
	 * Es werden die verfuegbaren Methoden ermittelt und ausgegeben.
	 */
	public function execute()
	{
		header('DAV: 1'); // Wir haben DAV-Level 1.
		header('Allow: '.implode(', ',$this->allowed_methods()) );
		
		Logger::trace('OPTIONS: '.'Allow: '.implode(', ',$this->allowed_methods()));

		$this->httpStatus( '200 OK' );
	}
}
