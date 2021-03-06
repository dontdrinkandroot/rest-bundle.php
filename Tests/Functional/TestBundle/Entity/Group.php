<?php

namespace Dontdrinkandroot\RestBundle\Tests\Functional\TestBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Dontdrinkandroot\RestBundle\Metadata\Annotation as REST;

/**
 * @author Philip Washington Sorst <philip@sorst.net>
 *
 * @ORM\Entity(
 *     repositoryClass="Dontdrinkandroot\Service\DoctrineCrudService"
 * )
 * @ORM\Table(name="`Group`")
 * @REST\RootResource(
 *     methods = {
 *         @REST\Method(name="GET")
 *     }
 * )
 */
class Group
{
    /**
     * @ORM\Column(type="integer", nullable=false)
     * @ORM\Id()
     * @ORM\GeneratedValue()
     *
     * @var int
     */
    private $id;

    /**
     * @ORM\Column(type="string", nullable=false)
     *
     * @var string
     */
    private $name;

    /**
     * @ORM\ManyToMany(targetEntity="User", inversedBy="groups")
     * @REST\SubResource(
     *     methods = {
     *         @REST\Method("PUT"),
     *         @REST\Method("DELETE")
     *     }
     * )
     * @REST\Includable()
     *
     * @var Collection|User[]
     */
    private $users;

    function __construct()
    {
        $this->users = new ArrayCollection();
    }

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Collection
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }
}
