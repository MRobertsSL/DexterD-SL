<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 10.10.14
 * Time: 17:59
 */
namespace Cundd\PersistentObjectStore\Server\Handler;

use Cundd\PersistentObjectStore\Server\ValueObject\RequestInfo;

/**
 * Interface for classes that handle the actions from incoming requests
 *
 * @package Cundd\PersistentObjectStore\Server\Handler
 */
interface HandlerInterface {
	/**
	 * Creates a new Data instance with the given data for the given RequestInfo
	 *
	 * @param RequestInfo $requestInfo
	 * @param mixed $data
	 * @return HandlerResultInterface
	 */
	public function create(RequestInfo $requestInfo, $data);

	/**
	 * Read Data instances for the given RequestInfo
	 *
	 * @param RequestInfo $requestInfo
	 * @return HandlerResultInterface
	 */
	public function read(RequestInfo $requestInfo);

	/**
	 * Update a Data instance with the given data for the given RequestInfo
	 *
	 * @param RequestInfo $requestInfo
	 * @param mixed $data
	 * @return HandlerResultInterface
	 */
	public function update(RequestInfo $requestInfo, $data);

	/**
	 * Deletes a Data instance for the given RequestInfo
	 *
	 * @param RequestInfo $requestInfo
	 * @return HandlerResultInterface
	 */
	public function delete(RequestInfo $requestInfo);

	/**
	 * Action to display server statistics
	 *
	 * @param RequestInfo $requestInfo
	 * @return HandlerResultInterface
	 */
	public function getStatsAction(RequestInfo $requestInfo);
}