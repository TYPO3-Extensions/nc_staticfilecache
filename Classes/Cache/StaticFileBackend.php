<?php
/**
 * Cache backend for static file cache
 *
 * @package Hdnet
 * @author  Tim Lochmüller
 */

namespace SFC\NcStaticfilecache\Cache;

use TYPO3\CMS\Core\Cache\Backend\AbstractBackend;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Cache backend for static file cache
 *
 * At the moment the backend write files only
 * - CacheFileName
 * - CacheFileName.gz
 *
 * @author Tim Lochmüller
 */
class StaticFileBackend extends AbstractBackend {

	/**
	 * Cache directory
	 *
	 * @var string
	 */
	protected $cacheDirectory = 'typo3temp/tx_ncstaticfilecache/';

	/**
	 * Configuration
	 *
	 * @var \SFC\NcStaticfilecache\Configuration
	 */
	protected $configuration;

	/**
	 * Build up the object
	 */
	public function __construct() {
		$this->configuration = GeneralUtility::makeInstance('SFC\\NcStaticfilecache\\Configuration');
	}

	/**
	 * Saves data in the cache.
	 *
	 * @param string  $entryIdentifier An identifier for this specific cache entry
	 * @param string  $data            The data to be stored
	 * @param array   $tags            Tags to associate with this cache entry. If the backend does not support tags, this option can be ignored.
	 * @param integer $lifetime        Lifetime of this cache entry in seconds. If NULL is specified, the default lifetime is used. "0" means unlimited lifetime.
	 *
	 * @return void
	 * @throws \TYPO3\CMS\Core\Cache\Exception if no cache frontend has been set.
	 * @throws \TYPO3\CMS\Core\Cache\Exception\InvalidDataException if the data is not a string
	 */
	public function set($entryIdentifier, $data, array $tags = array(), $lifetime = NULL) {
		$fileName = $this->getCacheFilename($entryIdentifier);
		$cacheDir = pathinfo($fileName, PATHINFO_DIRNAME);
		if (!is_dir($cacheDir)) {
			GeneralUtility::mkdir_deep($cacheDir);
		}

		// normal
		GeneralUtility::writeFile($fileName, $data);

		// gz
		if ($this->configuration->get('enableStaticFileCompression')) {
			$level = MathUtility::canBeInterpretedAsInteger($GLOBALS['TYPO3_CONF_VARS']['FE']['compressionLevel']) ? (int)$GLOBALS['TYPO3_CONF_VARS']['FE']['compressionLevel'] : 3;
			$contentGzip = gzencode($data, $level);
			if ($contentGzip) {
				GeneralUtility::writeFile($fileName . '.gz', $contentGzip);
			}
		}
	}

	/**
	 * Get the cache folder for the given entry
	 *
	 * @param $entryIdentifier
	 *
	 * @return string
	 */
	protected function getCacheFilename($entryIdentifier) {
		$urlParts = parse_url($entryIdentifier);
		$cacheFilename = GeneralUtility::getFileAbsFileName($this->cacheDirectory . $urlParts['host'] . '/' . trim($urlParts['path'], '/'));
		$fileExtension = pathinfo(basename($cacheFilename), PATHINFO_EXTENSION);
		if (empty($fileExtension) || !GeneralUtility::inList($this->configuration->get('fileTypes'), $fileExtension)) {
			$cacheFilename .= '/index.html';
		}
		return $cacheFilename;
	}

	/**
	 * Loads data from the cache.
	 *
	 * @param string $entryIdentifier An identifier which describes the cache entry to load
	 *
	 * @return mixed The cache entry's content as a string or FALSE if the cache entry could not be loaded
	 */
	public function get($entryIdentifier) {
		if (!$this->has($entryIdentifier)) {
			return NULL;
		}
		return GeneralUtility::getUrl($this->getCacheFilename($entryIdentifier));
	}

	/**
	 * Checks if a cache entry with the specified identifier exists.
	 *
	 * @param string $entryIdentifier An identifier specifying the cache entry
	 *
	 * @return boolean TRUE if such an entry exists, FALSE if not
	 */
	public function has($entryIdentifier) {
		return is_file($this->getCacheFilename($entryIdentifier));
	}

	/**
	 * Removes all cache entries matching the specified identifier.
	 * Usually this only affects one entry but if - for what reason ever -
	 * old entries for the identifier still exist, they are removed as well.
	 *
	 * @param string $entryIdentifier Specifies the cache entry to remove
	 *
	 * @return boolean TRUE if (at least) an entry could be removed or FALSE if no entry was found
	 */
	public function remove($entryIdentifier) {
		if (!$this->has($entryIdentifier)) {
			return FALSE;
		}
		$fileName = $this->getCacheFilename($entryIdentifier);
		unlink($fileName);
		if (is_file($fileName . '.gz')) {
			unlink($fileName . '.gz');
		}
		return TRUE;
	}

	/**
	 * Removes all cache entries of this cache.
	 *
	 * @return void
	 */
	public function flush() {
		GeneralUtility::rmdir(GeneralUtility::getFileAbsFileName($this->cacheDirectory), TRUE);
	}

	/**
	 * Does garbage collection
	 *
	 * @return void
	 */
	public function collectGarbage() {

	}
}
