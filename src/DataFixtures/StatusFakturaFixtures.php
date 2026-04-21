<?php

namespace App\DataFixtures;

use App\Entity\StatusFaktura;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class StatusFakturaFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $statusy = [
            ['name' => 'Nová', 'color' => 'blue', 'sortOrder' => 1],
            ['name' => 'Čeká na platbu', 'color' => 'yellow', 'sortOrder' => 2],
            ['name' => 'Zaplaceno', 'color' => 'green', 'sortOrder' => 3],
            ['name' => 'Po splatnosti', 'color' => 'red', 'sortOrder' => 4],
            ['name' => 'Storno', 'color' => 'gray', 'sortOrder' => 5],
        ];

        foreach ($statusy as $data) {
            $status = new StatusFaktura();
            $status->setName($data['name']);
            $status->setColor($data['color']);
            $status->setSortOrder($data['sortOrder']);
            $manager->persist($status);
        }

        $manager->flush();
    }
}
