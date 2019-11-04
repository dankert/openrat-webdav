<?php

class DAV_LOCK extends DAV
{
	/**
	 * Die Methode LOCK sollte garnicht aufgerufen werden, da wir nur
	 * Dav-Level 1 implementieren und dies dem Client auch mitteilen.<br>
	 * <br>
	 * Ausgabe von HTTP-Status 412 (Precondition failed)
	 */	
	function execute()
	{
		$this->httpStatus('412 Precondition failed');
		$this->davOPTIONS();
	}
}
