<?php

namespace dav;

class MultistatusObject
{
	const TYPE_FOLDER = 'folder';
	const TYPE_FILE   = 'file';

	public $type = self::TYPE_FILE;
	public $creationDate = TIME_20000101;
	public $lastmodifiedDate = TIME_20000101;
	public $name = '';
	public $label = '';
	public $size = 0;
	public $mimeType = '';
}