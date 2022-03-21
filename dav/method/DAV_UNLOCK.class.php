<?php

namespace dav\method;

use dav\DAV;

class DAV_UNLOCK extends DAV
{


	/**
	 * Die Methode UNLOCK sollte garnicht aufgerufen werden, da wir nur
	 * Dav-Level 1 implementieren und dies dem Client auch mitteilen.<br>
	 * <br>
	 * Ausgabe von HTTP-Status 412 (Precondition failed)
	 */	
	public function execute()
	{
		$this->httpStatus('412 Precondition failed');
		header('Allow: '.implode(', ',$this->allowed_methods()) );
	}
}
