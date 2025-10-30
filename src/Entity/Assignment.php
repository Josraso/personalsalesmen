<?php
/**
 * Assignment Entity
 */

declare(strict_types=1);

namespace PrestaShop\Module\PersonalSalesmen\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="personalsalesmen_assignment")
 * @ORM\Entity(repositoryClass="PrestaShop\Module\PersonalSalesmen\Repository\AssignmentRepository")
 */
class Assignment
{
    /**
     * @ORM\Id
     * @ORM\Column(name="id_assignment", type="integer", options={"unsigned"=true})
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $id;

    /**
     * @ORM\Column(name="id_employee", type="integer", options={"unsigned"=true})
     */
    private int $idEmployee;

    /**
     * @ORM\Column(name="id_customer", type="integer", nullable=true, options={"unsigned"=true})
     */
    private ?int $idCustomer = null;

    /**
     * @ORM\Column(name="id_group", type="integer", nullable=true, options={"unsigned"=true})
     */
    private ?int $idGroup = null;

    /**
     * @ORM\Column(name="active", type="boolean")
     */
    private bool $active = true;

    /**
     * @ORM\Column(name="date_add", type="datetime")
     */
    private \DateTime $dateAdd;

    /**
     * @ORM\Column(name="date_upd", type="datetime")
     */
    private \DateTime $dateUpd;

    public function __construct()
    {
        $this->dateAdd = new \DateTime();
        $this->dateUpd = new \DateTime();
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getIdEmployee(): int
    {
        return $this->idEmployee;
    }

    public function getIdCustomer(): ?int
    {
        return $this->idCustomer;
    }

    public function getIdGroup(): ?int
    {
        return $this->idGroup;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getDateAdd(): \DateTime
    {
        return $this->dateAdd;
    }

    public function getDateUpd(): \DateTime
    {
        return $this->dateUpd;
    }

    // Setters
    public function setIdEmployee(int $idEmployee): self
    {
        $this->idEmployee = $idEmployee;
        return $this;
    }

    public function setIdCustomer(?int $idCustomer): self
    {
        $this->idCustomer = $idCustomer;
        return $this;
    }

    public function setIdGroup(?int $idGroup): self
    {
        $this->idGroup = $idGroup;
        return $this;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        $this->dateUpd = new \DateTime();
        return $this;
    }

    public function setDateUpd(\DateTime $dateUpd): self
    {
        $this->dateUpd = $dateUpd;
        return $this;
    }

    /**
     * Validar que solo se asigne cliente O grupo, no ambos
     */
    public function validate(): bool
    {
        return ($this->idCustomer !== null && $this->idGroup === null) ||
               ($this->idCustomer === null && $this->idGroup !== null);
    }

    /**
     * Obtener tipo de asignación
     */
    public function getAssignmentType(): string
    {
        return $this->idCustomer !== null ? 'customer' : 'group';
    }
}