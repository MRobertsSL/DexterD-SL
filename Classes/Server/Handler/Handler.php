<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 10.10.14
 * Time: 17:59
 */
namespace Cundd\PersistentObjectStore\Server\Handler;

use Cundd\PersistentObjectStore\Constants;
use Cundd\PersistentObjectStore\DataAccess\Exception\ReaderException;
use Cundd\PersistentObjectStore\Domain\Model\Document;
use Cundd\PersistentObjectStore\Domain\Model\DatabaseInterface;
use Cundd\PersistentObjectStore\Domain\Model\DocumentInterface;
use Cundd\PersistentObjectStore\Server\Exception\InvalidBodyException;
use Cundd\PersistentObjectStore\Server\Exception\InvalidRequestParameterException;
use Cundd\PersistentObjectStore\Server\ValueObject\HandlerResult;
use Cundd\PersistentObjectStore\Server\ValueObject\RequestInfo;

/**
 * Handler implementation
 *
 * @package Cundd\PersistentObjectStore\Server\Handler
 */
class Handler implements HandlerInterface {
	/**
	 * Document Access Coordinator
	 *
	 * @var \Cundd\PersistentObjectStore\DataAccess\CoordinatorInterface
	 * @Inject
	 */
	protected $coordinator;

	/**
	 * Server instance
	 *
	 * @var \Cundd\PersistentObjectStore\Server\ServerInterface
	 * @Inject
	 */
	protected $server;

	/**
	 * FilterBuilder instance
	 *
	 * @var \Cundd\PersistentObjectStore\Filter\FilterBuilderInterface
	 * @Inject
	 */
	protected $filterBuilder;

	/**
	 * Event Emitter
	 *
	 * @var \Evenement\EventEmitterInterface
	 * @Inject
	 */
	protected $eventEmitter;

	/**
	 * Invoked if no route is given (e.g. if the request path is empty)
	 *
	 * @param RequestInfo $requestInfo
	 * @return HandlerResultInterface
	 */
	public function noRoute(RequestInfo $requestInfo) {
		return new HandlerResult(200, Constants::MESSAGE_JSON_WELCOME);
	}


	/**
	 * Creates a new Document instance or Database with the given data for the given RequestInfo
	 *
	 * @param RequestInfo $requestInfo
	 * @param mixed       $data
	 * @return HandlerResultInterface
	 */
	public function create(RequestInfo $requestInfo, $data) {
		if ($requestInfo->getMethod() === 'POST') { // Create a Document instance
			return $this->_createDataInstance($requestInfo, $data);
		}

		if ($requestInfo->getMethod() === 'PUT') { // Create a Database
			return $this->_createDatabase($requestInfo, $data);
		}
		return new HandlerResult(400, sprintf('Invalid HTTP method %s', $requestInfo->getMethod()));
	}

	/**
	 * Creates and returns a new Document instance
	 *
	 * @param RequestInfo $requestInfo
	 * @param mixed       $data
	 * @return HandlerResult
	 */
	protected function _createDataInstance(RequestInfo $requestInfo, $data) {
		$database = $this->getDatabaseForRequestInfo($requestInfo);
		$document = new Document($data);

		if ($requestInfo->getDataIdentifier()) throw new InvalidRequestParameterException(
			'Document identifier in request path is not allowed when creating a Document instance. Use PUT to update',
			1413278767
		);
		if ($database->contains($document)) throw new InvalidBodyException(
			sprintf(
				'Database \'%s\' already contains the given data. Maybe the values of the identifier are not expressive',
				$database->getIdentifier()
			),
			1413215990
		);

		$database->add($document);
			$this->eventEmitter->emit(Event::DOCUMENT_CREATED, array($document));
			return new HandlerResult(
				201,
				$document
			);
	}

	/**
	 * Creates and returns a new Database
	 *
	 * @param RequestInfo $requestInfo
	 * @param mixed       $data
	 * @return HandlerResult
	 */
	protected function _createDatabase(RequestInfo $requestInfo, $data) {
		if ($requestInfo->getDataIdentifier()) throw new InvalidRequestParameterException(
			'Document identifier in request path is not allowed when creating a Database',
			1413278767
		);

		$databaseIdentifier = $requestInfo->getDatabaseIdentifier();
		$database = $this->coordinator->createDatabase($databaseIdentifier);
		if ($database) {
			$this->eventEmitter->emit(Event::DATABASE_CREATED, array($database));
			return new HandlerResult(201, sprintf('Database "%s" created', $databaseIdentifier));
		} else {
			return new HandlerResult(400);
		}
	}

	/**
	 * Read Document instances for the given RequestInfo
	 *
	 * @param RequestInfo $requestInfo
	 * @return HandlerResultInterface
	 */
	public function read(RequestInfo $requestInfo) {
		if ($requestInfo->getDataIdentifier()) { // Load Document instance
			$document = $this->getDataForRequest($requestInfo);
			if ($document) {
				return new HandlerResult(
					200,
					$document
				);
			} else {
				return new HandlerResult(
					404,
					sprintf(
						'Document instance with identifier "%s" not found in database "%s"',
						$requestInfo->getDataIdentifier(),
						$requestInfo->getDatabaseIdentifier()
					)
				);
			}
		}

		$database = $this->getDatabaseForRequestInfo($requestInfo);
		if (!$database) {
			return new HandlerResult(
				404,
				sprintf(
					'Database with identifier "%s" not found',
					$requestInfo->getDatabaseIdentifier()
				)
			);
		}

		if (!$requestInfo->getRequest()->getQuery()) {
			return new HandlerResult(200, $database);
		}

		$filterResult = $this->filterBuilder->buildFilterFromQueryParts($requestInfo->getRequest()->getQuery(), $database);
		$statusCode = $filterResult->count() > 0 ? 200 : 404;
		return new HandlerResult($statusCode, $filterResult);
	}

	/**
	 * Update a Document instance with the given data for the given RequestInfo
	 *
	 * @param RequestInfo $requestInfo
	 * @param mixed       $data
	 * @return HandlerResultInterface
	 */
	public function update(RequestInfo $requestInfo, $data) {
		if (!$requestInfo->getDataIdentifier()) throw new InvalidRequestParameterException('Document identifier is missing', 1413292389);
		$document = $this->getDataForRequest($requestInfo);
		if (!$document) {
			return new HandlerResult(404, sprintf(
				'Document instance with identifier "%s" not found in database "%s"',
				$requestInfo->getDataIdentifier(),
				$requestInfo->getDatabaseIdentifier()
			));
		}

		$database = $this->getDatabaseForRequestInfo($requestInfo);

		$data[Constants::DATA_ID_KEY] = $requestInfo->getDataIdentifier();
		$newDocument = new Document($data, $database->getIdentifier());
		$database->update($newDocument);
		$this->eventEmitter->emit(Event::DOCUMENT_UPDATED, array($document));
		return new HandlerResult(200, $newDocument);
	}

	/**
	 * Deletes a Document instance for the given RequestInfo
	 *
	 * @param RequestInfo $requestInfo
	 * @return HandlerResultInterface
	 */
	public function delete(RequestInfo $requestInfo) {
		$database = $this->getDatabaseForRequestInfo($requestInfo);
		if (!$database) {
			throw new InvalidRequestParameterException(
				sprintf(
					'Database with identifier "%s" not found',
					$requestInfo->getDatabaseIdentifier()
				),
				1413035859
			);
		}


//		if (!$requestInfo->getDataIdentifier()) throw new InvalidRequestParameterException('Document identifier is missing', 1413035855);
		if ($requestInfo->getDataIdentifier()) {
			$document = $this->getDataForRequest($requestInfo);
			if (!$document) {
				throw new InvalidRequestParameterException(
					sprintf(
						'Document with identifier "%s" not found in database "%s"',
						$requestInfo->getDataIdentifier(),
						$requestInfo->getDatabaseIdentifier()
					),
					1413035855
				);
			}
			$database->remove($document);

			$this->eventEmitter->emit(Event::DOCUMENT_DELETED, array($document));
			return new HandlerResult(204, sprintf('Document "%s" deleted', $requestInfo->getDataIdentifier()));
		}

		$databaseIdentifier = $database->getIdentifier();
		$this->coordinator->dropDatabase($databaseIdentifier);
		$this->eventEmitter->emit(Event::DATABASE_DELETED, array($database));
		return new HandlerResult(204, sprintf('Database "%s" deleted', $databaseIdentifier));
	}

	/**
	 * Action to display server statistics
	 *
	 * @param RequestInfo $requestInfo
	 * @return HandlerResultInterface
	 */
	public function getStatsAction(RequestInfo $requestInfo) {
		$detailedStatistics = $requestInfo->getDataIdentifier() === 'detailed';
		return new HandlerResult(200, $this->server->collectStatistics($detailedStatistics));
	}

	/**
	 * Action to display all databases
	 *
	 * @param RequestInfo $requestInfo
	 * @return HandlerResultInterface
	 */
	public function getAllDbsAction(RequestInfo $requestInfo) {
		return new HandlerResult(200, $this->coordinator->listDatabases());
	}

	/**
	 * Returns the database for the given request or NULL if it is not specified
	 *
	 * @param RequestInfo $requestInfo
	 * @return DatabaseInterface|NULL
	 */
	public function getDatabaseForRequestInfo(RequestInfo $requestInfo) {
		if (!$requestInfo->getDatabaseIdentifier()) {
			return NULL;
		}
		$databaseIdentifier = $requestInfo->getDatabaseIdentifier();
//		if (!$this->coordinator->databaseExists($databaseIdentifier)) {
//			return NULL;
//		}
		try {
			return $this->coordinator->getDatabase($databaseIdentifier);
		} catch (ReaderException $exception) {
			return NULL;
		}
	}

	/**
	 * Returns the Document for the given request or NULL if it is not specified
	 *
	 * @param RequestInfo $requestInfo
	 * @return DocumentInterface|NULL
	 */
	public function getDataForRequest(RequestInfo $requestInfo) {
		if (!$requestInfo->getDataIdentifier()) {
			return NULL;
		}
		$database = $this->getDatabaseForRequestInfo($requestInfo);
		return $database ? $database->findByIdentifier($requestInfo->getDataIdentifier()) : NULL;
	}


}