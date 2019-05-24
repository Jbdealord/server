<?php
declare(strict_types=1);


/**
 * Nextcloud - Social Support
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OC\Entities\Db;


use DateTime;
use OC\Entities\Exceptions\EntityNotFoundException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Entities\Implementation\IEntities\IEntitiesSearchEntities;
use OCP\Entities\Implementation\IEntitiesAccounts\IEntitiesAccountsSearchEntities;
use OCP\Entities\Model\IEntity;
use stdClass;

/**
 * Class EntitiesRequest
 *
 * @package OC\Entities\Db
 */
class EntitiesRequest extends EntitiesRequestBuilder {


	/**
	 * @param IEntity $entity
	 */
	public function create(IEntity $entity) {
		$now = new DateTime('now');

		$qb = $this->getEntitiesInsertSql();
		$qb->setValue('id', $qb->createNamedParameter($entity->getId()))
		   ->setValue('type', $qb->createNamedParameter($entity->getType()))
		   ->setValue('owner_id', $qb->createNamedParameter($entity->getOwnerId()))
		   ->setValue('visibility', $qb->createNamedParameter($entity->getVisibility()))
		   ->setValue('access', $qb->createNamedParameter($entity->getAccess()))
		   ->setValue('name', $qb->createNamedParameter($entity->getName()))
		   ->setValue('creation', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATE));
		$qb->execute();

		$entity->setCreation($now->getTimestamp());
	}


	/**
	 * @param string $entityId
	 *
	 * @return IEntity
	 * @throws EntityNotFoundException
	 */
	public function getFromId(string $entityId): IEntity {
		$qb = $this->getEntitiesSelectSql();
		$qb->leftJoinEntityAccount('owner_id');
		$qb->limitToIdString($entityId);

		return $this->getItemFromRequest($qb);
	}


	/**
	 * @param string $type
	 *
	 * @return IEntity[]
	 */
	public function getAll(string $type = ''): array {
		$qb = $this->getEntitiesSelectSql();
		if ($type !== '') {
			$qb->limitToType($type);
		}

		$qb->orderBy('type', 'asc');
		$qb->leftJoinEntityAccount('owner_id');

		return $this->getListFromRequest($qb);
	}


	/**
	 * @param string $needle
	 * @param string $type
	 * @param stdClass[] $classes
	 *
	 * @return IEntity[]
	 */
	public function search(string $needle, string $type = '', array $classes = []): array {
		$qb = $this->getEntitiesSelectSql();
		if ($type !== '') {
			$qb->limitToType($type);
		}

		$qb->orderBy('type', 'asc');
		$qb->leftJoinEntityAccount('owner_id');

		$needle = $this->dbConnection->escapeLikeParameter($needle);
		$qb->searchInName('%' . $needle . '%');

		if (sizeof($classes) > 0) {
			$orX = $qb->expr()
					  ->orX();
			foreach ($classes as $class) {
				/** @var IEntitiesAccountsSearchEntities|IEntitiesSearchEntities $class */
				$orX->add($class->exprSearchEntities($qb, $needle));
			}

			$qb->orWhere($orX);
		}

		return $this->getListFromRequest($qb);
	}


	public function getMembership(IEntity $entity) {
	}


	/**
	 * @param IQueryBuilder $qb
	 *
	 * @return IEntity
	 * @throws EntityNotFoundException
	 */
	public function getItemFromRequest(IQueryBuilder $qb): IEntity {
		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new EntityNotFoundException();
		}

		return $this->parseEntitiesSelectSql($data);
	}


	/**
	 * @param IQueryBuilder $qb
	 *
	 * @return IEntity[]
	 */
	public function getListFromRequest(IQueryBuilder $qb): array {
		$entities = [];
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$entities[] = $this->parseEntitiesSelectSql($data);
		}
		$cursor->closeCursor();

		return $entities;
	}


	/**
	 *
	 */
	public function clearAll(): void {
		$qb = $this->getEntitiesDeleteSql();

		$qb->execute();
	}

}

