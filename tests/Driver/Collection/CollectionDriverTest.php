<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataSource\Tests\Driver\Doctrine;

use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use FSi\Component\DataSource\DataSourceFactory;
use FSi\Component\DataSource\DataSourceInterface;
use FSi\Component\DataSource\Driver\Collection\CollectionFactory;
use FSi\Component\DataSource\Driver\Collection\Exception\CollectionDriverException;
use FSi\Component\DataSource\Driver\Collection\Extension\Core\CoreExtension;
use FSi\Component\DataSource\Driver\DriverFactoryManager;
use FSi\Component\DataSource\Extension\Core;
use FSi\Component\DataSource\Extension\Core\Ordering\OrderingExtension;
use FSi\Component\DataSource\Extension\Core\Pagination\PaginationExtension;
use FSi\Component\DataSource\Field\FieldTypeInterface;
use FSi\Component\DataSource\Tests\Fixtures\Category;
use FSi\Component\DataSource\Tests\Fixtures\Group;
use FSi\Component\DataSource\Tests\Fixtures\News;

/**
 * Tests for Doctrine driver.
 */
class CollectionDriverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        //The connection configuration.
        $dbParams = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        $config = Setup::createAnnotationMetadataConfiguration([__DIR__ . '/../../Fixtures'], true, null, null, false);
        $em = EntityManager::create($dbParams, $config);
        $tool = new SchemaTool($em);
        $classes = [
            $em->getClassMetadata(News::class),
            $em->getClassMetadata(Category::class),
            $em->getClassMetadata(Group::class),
        ];
        $tool->createSchema($classes);
        $this->load($em);
        $this->em = $em;
    }

    /**
     * Test number field when comparing with 0 value.
     */
    public function testComparingWithZero()
    {
        $datasourceFactory = $this->getDataSourceFactory();
        $driverOptions = [
            'collection' => $this->em->getRepository(News::class)->findAll(),
        ];

        $datasource = $datasourceFactory
            ->createDataSource('collection', $driverOptions, 'datasource')
            ->addField('id', 'number', 'eq');

        $parameters = [
            $datasource->getName() => [
                DataSourceInterface::PARAMETER_FIELDS => [
                    'id' => '0',
                ],
            ],
        ];
        $datasource->bindParameters($parameters);
        $result = $datasource->getResult();
        $this->assertEquals(0, count($result));
    }

    /**
     * General test for DataSource wtih CollectionDriver in basic configuration.
     */
    public function testGeneral()
    {
        $datasourceFactory = $this->getDataSourceFactory();

        $driverFactory = $this->getCollectionFactory();
        $driver = $driverFactory->createDriver();

        $datasources = [];

        $driverOptions = [
            'collection' => $this->em->getRepository(News::class)->findAll(),
        ];

        $datasources[] = $datasourceFactory->createDataSource('collection', $driverOptions, 'datasource');

        $qb = $this->em
            ->createQueryBuilder()
            ->select('n')
            ->from(News::class, 'n')
        ;

        $driverOptions = [
            'collection' => $qb->getQuery()->execute()
        ];

        $datasources[] = $datasourceFactory->createDataSource('collection', $driverOptions, 'datasource2');

        foreach ($datasources as $datasource) {
            $datasource
                ->addField('title', 'text', 'contains')
                ->addField('author', 'text', 'contains')
                ->addField('created', 'datetime', 'between', [
                    'field' => 'create_date',
                ])
            ;

            $result1 = $datasource->getResult();
            $this->assertEquals(100, count($result1));
            $view1 = $datasource->createView();

            //Checking if result cache works.
            $this->assertSame($result1, $datasource->getResult());

            $parameters = [
                $datasource->getName() => [
                    DataSourceInterface::PARAMETER_FIELDS => [
                        'author' => 'domain1.com',
                    ],
                ],
            ];
            $datasource->bindParameters($parameters);
            $result2 = $datasource->getResult();

            //Checking cache.
            $this->assertSame($result2, $datasource->getResult());

            $this->assertEquals(50, count($result2));
            $this->assertNotSame($result1, $result2);
            unset($result1);
            unset($result2);

            $this->assertEquals($parameters, $datasource->getParameters());

            $datasource->setMaxResults(20);
            $parameters = [
                $datasource->getName() => [
                    PaginationExtension::PARAMETER_PAGE => 1,
                ],
            ];

            $datasource->bindParameters($parameters);
            $result = $datasource->getResult();
            $this->assertEquals(100, count($result));
            $i = 0;
            foreach ($result as $item) {
                $i++;
            }
            $this->assertEquals(20, $i);

            $parameters = [
                $datasource->getName() => [
                    DataSourceInterface::PARAMETER_FIELDS => [
                        'author' => 'domain1.com',
                        'title' => 'title3',
                        'created' => [
                            'from' => new DateTime(date("Y:m:d H:i:s", 35 * 24 * 60 * 60)),
                        ],
                    ],
                ],
            ];
            $datasource->bindParameters($parameters);
            $view = $datasource->createView();
            $result = $datasource->getResult();
            $this->assertEquals(2, count($result));

            $parameters = [
                $datasource->getName() => [
                    DataSourceInterface::PARAMETER_FIELDS => [
                        'author' => 'author3@domain2.com',
                    ],
                ]
            ];
            $datasource->bindParameters($parameters);
            $view = $datasource->createView();
            $result = $datasource->getResult();
            $this->assertEquals(1, count($result));

            //Checking sorting.
            $parameters = [
                $datasource->getName() => [
                    OrderingExtension::PARAMETER_SORT => [
                        'title' => 'desc'
                    ],
                ],
            ];

            $datasource->bindParameters($parameters);
            foreach ($datasource->getResult() as $news) {
                $this->assertEquals('title99', $news->getTitle());
                break;
            }

            //Checking sorting.
            $parameters = [
                $datasource->getName() => [
                    OrderingExtension::PARAMETER_SORT => [
                        'author' => 'asc',
                        'title' => 'desc',
                    ],
                ],
            ];

            $datasource->bindParameters($parameters);
            foreach ($datasource->getResult() as $news) {
                $this->assertEquals('author0@domain1.com', $news->getAuthor());
                break;
            }

            //Test for clearing fields.
            $datasource->clearFields();
            $datasource->setMaxResults(null);
            $parameters = [
                $datasource->getName() => [
                    DataSourceInterface::PARAMETER_FIELDS => [
                        'author' => 'domain1.com',
                    ],
                ],
            ];

            //Since there are no fields now, we should have all of entities.
            $datasource->bindParameters($parameters);
            $result = $datasource->getResult();
            $this->assertEquals(100, count($result));

            //Test boolean field
            $datasource
                ->addField('active', 'boolean', 'eq')
            ;
            $datasource->setMaxResults(null);
            $parameters = [
                $datasource->getName() => [
                    DataSourceInterface::PARAMETER_FIELDS => [
                        'active' => 1,
                    ],
                ]
            ];

            $datasource->bindParameters($parameters);
            $view = $datasource->createView();
            $result = $datasource->getResult();
            $this->assertEquals(50, count($result));

            $parameters = [
                $datasource->getName() => [
                    DataSourceInterface::PARAMETER_FIELDS => [
                        'active' => 0,
                    ],
                ]
            ];

            $datasource->bindParameters($parameters);
            $view = $datasource->createView();
            $result = $datasource->getResult();
            $this->assertEquals(50, count($result));

            $parameters = [
                $datasource->getName() => [
                    DataSourceInterface::PARAMETER_FIELDS => [
                        'active' => true,
                    ],
                ]
            ];

            $datasource->bindParameters($parameters);
            $view = $datasource->createView();
            $result = $datasource->getResult();
            $this->assertEquals(50, count($result));

            $parameters = [
                $datasource->getName() => [
                    DataSourceInterface::PARAMETER_FIELDS => [
                        'active' => false,
                    ],
                ]
            ];

            $datasource->bindParameters($parameters);
            $view = $datasource->createView();
            $result = $datasource->getResult();
            $this->assertEquals(50, count($result));

            $parameters = [
                $datasource->getName() => [
                    DataSourceInterface::PARAMETER_FIELDS => [
                        'active' => null,
                    ],
                ]
            ];

            $datasource->bindParameters($parameters);
            $view = $datasource->createView();
            $result = $datasource->getResult();
            $this->assertEquals(100, count($result));

            $parameters = [
                $datasource->getName() => [
                    OrderingExtension::PARAMETER_SORT => [
                        'active' => 'desc'
                    ],
                ],
            ];

            $datasource->bindParameters($parameters);
            foreach ($datasource->getResult() as $news) {
                $this->assertEquals(true, $news->isActive());
                break;
            }

            $parameters = [
                $datasource->getName() => [
                    OrderingExtension::PARAMETER_SORT => [
                        'active' => 'asc'
                    ],
                ],
            ];

            $datasource->bindParameters($parameters);
            foreach ($datasource->getResult() as $news) {
                $this->assertEquals(false, $news->isActive());
                break;
            }

            // test 'notIn' comparison
            $datasource->addField('title_is_not', 'text', 'notIn', [
                'field' => 'title',
            ]);

            $parameters = [
                $datasource->getName() => [
                    DataSourceInterface::PARAMETER_FIELDS => [
                        'title_is_not' => ['title1', 'title2', 'title3']
                    ],
                ],
            ];

            $datasource->bindParameters($parameters);
            $view = $datasource->createView();
            $result = $datasource->getResult();
            $this->assertEquals(97, count($result));
        }
    }

    public function testExceptions()
    {
        $datasourceFactory = $this->getDataSourceFactory();

        $driverFactory = $this->getCollectionFactory();
        $driver = $driverFactory->createDriver();

        $driverOptions = [
            'collection' => $this->em->getRepository(News::class)->findAll(),
        ];

        $datasource = $datasourceFactory->createDataSource('collection', $driverOptions, 'datasource');
        $field = $this->createMock(FieldTypeInterface::class);

        $field
            ->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('example'))
        ;

        $datasource->addField($field);

        $this->setExpectedException(CollectionDriverException::class);
        $result1 = $datasource->getResult();
    }

    public function testExceptions2()
    {
        $datasourceFactory = $this->getDataSourceFactory();

        $driverFactory = $this->getCollectionFactory();
        $driver = $driverFactory->createDriver();

        $this->setExpectedException(CollectionDriverException::class);
        $driver->getCriteria();
    }

    /**
     * {@inheritdoc}
     */
    public function tearDown()
    {
        unset($this->em);
    }

    /**
     * Return configured DoctrinFactory.
     *
     * @return CollectionFactory.
     */
    private function getCollectionFactory()
    {
        $extensions = [
            new CoreExtension(),
        ];

        return new CollectionFactory($extensions);
    }

    /**
     * Return configured DataSourceFactory.
     *
     * @return DataSourceFactory
     */
    private function getDataSourceFactory()
    {
        $driverFactoryManager = new DriverFactoryManager([
            $this->getCollectionFactory()
        ]);

        $extensions = [
            new Core\Pagination\PaginationExtension(),
            new OrderingExtension(),
        ];

        return new DataSourceFactory($driverFactoryManager, $extensions);
    }

    /**
     * @param EntityManagerInterface $em
     */
    private function load(EntityManagerInterface $em)
    {
        //Injects 5 categories.
        $categories = [];
        for ($i = 0; $i < 5; $i++) {
            $category = new Category();
            $category->setName('category'.$i);
            $em->persist($category);
            $categories[] = $category;
        }

        //Injects 4 groups.
        $groups = [];
        for ($i = 0; $i < 4; $i++) {
            $group = new Group();
            $group->setName('group'.$i);
            $em->persist($group);
            $groups[] = $group;
        }

        //Injects 100 newses.
        for ($i = 0; $i < 100; $i++) {
            $news = new News();
            $news->setTitle('title'.$i);

            //Half of entities will have different author and content.
            if ($i % 2 == 0) {
                $news->setAuthor('author'.$i.'@domain1.com');
                $news->setShortContent('Lorem ipsum.');
                $news->setContent('Content lorem ipsum.');
            } else {
                $news->setAuthor('author'.$i.'@domain2.com');
                $news->setShortContent('Dolor sit amet.');
                $news->setContent('Content dolor sit amet.');
                $news->setActive();
            }

            //Each entity has different date of creation and one of four hours of creation.
            $createDate = new DateTime(date("Y:m:d H:i:s", $i * 24 * 60 * 60));
            $createTime = new DateTime(date("H:i:s", (($i % 4) + 1 ) * 60 * 60));

            $news->setCreateDate($createDate);
            $news->setCreateTime($createTime);

            $news->setCategory($categories[$i % 5]);
            $news->getGroups()->add($groups[$i % 4]);

            $em->persist($news);
        }

        $em->flush();
    }
}
