<?php

/**
 * @file classes/file/TemporaryFileManager.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPTemporaryFileManager
 * @ingroup file
 * @see TemporaryFileDAO
 *
 * @brief Class defining operations for temporary file management.
 */


import('lib.pkp.classes.file.FileManager');

class PKPTemporaryFileManager extends FileManager {
	/**
	 * Constructor
	 */
	function PKPTemporaryFileManager() {
		parent::FileManager();
		$this->filesDir = Config::getVar('files', 'files_dir') . '/temp/';

		$this->_performPeriodicCleanup();
	}

	/**
	 * Retrieve file information by file ID.
	 * @return TemporaryFile
	 */
	function &getFile($fileId, $userId) {
		$temporaryFileDao =& DAORegistry::getDAO('TemporaryFileDAO');
		$temporaryFile =& $temporaryFileDao->getTemporaryFile($fileId, $userId);
		return $temporaryFile;
	}

	/**
	 * Read a file's contents.
	 * @param $output boolean output the file's contents instead of returning a string
	 * @return boolean
	 */
	function readFile($fileId, $userId, $output = false) {
		$temporaryFile =& $this->getFile($fileId, $userId);

		if (isset($temporaryFile)) {
			$filePath = $this->filesDir . $temporaryFile->getFileName();
			return parent::readFile($filePath, $output);
		} else {
			return false;
		}
	}

	/**
	 * Delete a file by ID.
	 * @param $fileId int
	 */
	function deleteFile($fileId, $userId) {
		$temporaryFile =& $this->getFile($fileId, $userId);

		parent::deleteFile($this->filesDir . $temporaryFile->getFileName());

		$temporaryFileDao =& DAORegistry::getDAO('TemporaryFileDAO');
		$temporaryFileDao->deleteTemporaryFileById($fileId, $userId);
	}

	/**
	 * Download a file.
	 * @param $fileId int the file id of the file to download
	 * @param $inline print file as inline instead of attachment, optional
	 * @return boolean
	 */
	function downloadFile($fileId, $userId, $inline = false) {
		$temporaryFile =& $this->getFile($fileId, $userId);
		if (isset($temporaryFile)) {
			$filePath = $this->filesDir . $temporaryFile->getFileName();
			return parent::downloadFile($filePath, null, $inline);
		} else {
			return false;
		}
	}

	/**
	 * View a file inline (variant of downloadFile).
	 * @see PKPTemporaryFileManager::downloadFile
	 */
	function viewFile($fileId) {
		$this->downloadFile($fileId, true);
	}

	/**
	 * Upload the file and add it to the database.
	 * @param $fileName string index into the $_FILES array
	 * @param $userId int
	 * @return object The new TemporaryFile or false on failure
	 */
	function handleUpload($fileName, $userId) {
		// Get the file extension, then rename the file.
		$fileExtension = $this->parseFileExtension($this->getUploadedFileName($fileName));

		if (!$this->fileExists($this->filesDir, 'dir')) {
			// Try to create destination directory
			$this->mkdirtree($this->filesDir);
		}

		$newFileName = basename(tempnam($this->filesDir, $fileExtension));
		if (!$newFileName) return false;

		if ($this->uploadFile($fileName, $this->filesDir . $newFileName)) {
			$temporaryFileDao =& DAORegistry::getDAO('TemporaryFileDAO');
			$temporaryFile = new TemporaryFile();

			$temporaryFile->setUserId($userId);
			$temporaryFile->setFileName($newFileName);
			$temporaryFile->setFileType($_FILES[$fileName]['type']);
			$temporaryFile->setFileSize($_FILES[$fileName]['size']);
			$temporaryFile->setOriginalFileName(TemporaryFileManager::truncateFileName($_FILES[$fileName]['name'], 127));
			$temporaryFile->setDateUploaded(Core::getCurrentDate());

			$temporaryFileDao->insertTemporaryFile($temporaryFile);

			return $temporaryFile;

		} else {
			return false;
		}
	}

	/**
	 * Perform periodic cleanup tasks. This is used to occasionally
	 * remove expired temporary files.
	 */
	function _performPeriodicCleanup() {
		if (time() % 100 == 0) {
			$temporaryFileDao =& DAORegistry::getDAO('TemporaryFileDAO');
			$expiredFiles = $temporaryFileDao->getExpiredFiles();
			foreach ($expiredFiles as $expiredFile) {
				$this->deleteFile($expiredFile->getId(), $expiredFile->getUserId());
			}
		}
	}
}

?>
