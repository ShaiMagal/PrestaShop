<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Tests\Integration\Adapter;

use Doctrine\ORM\EntityManager;
use Generator;
use PrestaShop\PrestaShop\Adapter\Configuration;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use PrestaShopBundle\Entity\Shop;
use PrestaShopBundle\Entity\ShopGroup;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ConfigurationTest extends KernelTestCase
{
    /**
     * @var Configuration|null
     */
    private $configuration;

    /**
     * @var EntityManager
     */
    private $entityManager;

    protected function setUp()
    {
        self::bootKernel();

        $container = self::$kernel->getContainer();
        $this->entityManager = $container->get('doctrine.orm.entity_manager');
        $this->configuration = $container->get('prestashop.adapter.legacy.configuration');

        $this->initMultistore();
    }

    /**
     * @param array $setParams
     * @param array $getParams
     * @param $expectedResult
     *
     * @dataProvider getProvider
     */
    public function testGet(array $setParams, array $getParams, $expectedResult): void
    {
        $this->setAndGetValuesForTesting($setParams, $getParams, $expectedResult);
    }

    /**
     * @param array $setParams
     * @param array $getParams
     * @param $expectedResult
     *
     * @dataProvider getWithStrictParameterProvider
     */
    public function testGetWithSrictParameter(array $setParams, array $getParams, $expectedResult)
    {
        $this->setAndGetValuesForTesting($setParams, $getParams, $expectedResult);
    }

    /**
     * @return Generator
     */
    public function getProvider(): Generator
    {
        // simple case: get an all shop config value
        yield [
            [
                [
                    'key' => 'key_test_1',
                    'value' => 'value_test_1',
                    'shopConstraint' => ShopConstraint::allShops(),
                ],
            ],
            [
                'key' => 'key_test_1',
                'default' => false,
                'shopConstraint' => ShopConstraint::allShops(),
            ],
            'value_test_1',
        ];
        // simple case: get a group shop config value
        yield [
            [
                [
                    'key' => 'key_test_2',
                    'value' => 'value_test_2',
                    'shopConstraint' => ShopConstraint::shop(2),
                ],
            ],
            [
                'key' => 'key_test_2',
                'default' => false,
                'shopConstraint' => ShopConstraint::shop(2),
            ],
            'value_test_2',
        ];
        // simple case: get a single shop config value
        yield [
            [
                [
                    'key' => 'key_test_3',
                    'value' => 'value_test_3',
                    'shopConstraint' => ShopConstraint::shopGroup(1),
                ],
            ],
            [
                'key' => 'key_test_3',
                'default' => false,
                'shopConstraint' => ShopConstraint::shopGroup(1),
            ],
            'value_test_3',
        ];
        // try to get a non existing value for all shop, get default value instead
        yield [
            [],
            [
                'key' => 'does_not_exist',
                'default' => 'default_value_all_shop',
                'shopConstraint' => ShopConstraint::allShops(),
            ],
            'default_value_all_shop',
        ];
        // try to get a non existing value for group shop, get default value instead
        yield [
            [],
            [
                'key' => 'does_not_exist',
                'default' => 'default_value',
                'shopConstraint' => ShopConstraint::shopGroup(1),
            ],
            'default_value',
        ];
        // try to get a non existing value for single shop, get default value instead
        yield [
            [],
            [
                'key' => 'does_not_exist',
                'default' => 'default_value',
                'shopConstraint' => ShopConstraint::shop(2),
            ],
            'default_value',
        ];
        // get value for a group shop, inherited from all shop
        yield [
            [
                [
                    'key' => 'all_shop_key_1',
                    'value' => 'all_shop_value_1',
                    'shopConstraint' => ShopConstraint::allShops(),
                ],
            ],
            [
                'key' => 'all_shop_key_1',
                'default' => false,
                'shopConstraint' => ShopConstraint::shopGroup(1),
            ],
            'all_shop_value_1',
        ];
        // get value for shop 2, inherited from parent group shop
        yield [
            [
                [
                    'key' => 'parent_group_key_1',
                    'value' => 'parent_group_value',
                    'shopConstraint' => ShopConstraint::shopGroup(1),
                ],
            ],
            [
                'key' => 'parent_group_key_1',
                'default' => false,
                'shopConstraint' => ShopConstraint::shop(2),
            ],
            'parent_group_value',
        ];
    }

    /**
     * @return Generator
     */
    public function getWithStrictParameterProvider(): Generator
    {
        // try getting a non existing value for a aingle shop, with is strict = true => should not inherit from parent group
        yield [
            [
                [
                    'key' => 'parent_group_key_2',
                    'value' => 'parent_group_value_2',
                    'shopConstraint' => ShopConstraint::shopGroup(1),
                ],
            ],
            [
                'key' => 'parent_group_key_2',
                'default' => false,
                'shopConstraint' => ShopConstraint::shop(2, true),
            ],
            null,
        ];
        // try getting a non existing value for a group, with is strict = true => should not inherit from all shop
        yield [
            [
                [
                    'key' => 'all_shop_key_1',
                    'value' => 'all_shop_value_1',
                    'shopConstraint' => ShopConstraint::allShops(),
                ],
            ],
            [
                'key' => 'all_shop_key_1',
                'default' => false,
                'shopConstraint' => ShopConstraint::shopGroup(1, true),
            ],
            null,
        ];
        // try getting a non existing value for all shop, with is strict = true => should return null
        yield [
            [],
            [
                'key' => 'does_not_exist',
                'default' => false,
                'shopConstraint' => ShopConstraint::allShops(true),
            ],
            null,
        ];
    }

    /**
     * @param array $setParams
     * @param array $getParams
     * @param $expectedResult
     */
    private function setAndGetValuesForTesting(array $setParams, array $getParams, $expectedResult): void
    {
        foreach ($setParams as $values) {
            $this->configuration->set($values['key'], $values['value'], $values['shopConstraint']);
        }
        $result = $this->configuration->get($getParams['key'], $getParams['default'], $getParams['shopConstraint']);

        $this->assertEquals($expectedResult, $result);
    }

    private function initMultistore(): void
    {
        // we want to execute this only once for the whole class
        $flag = $this->configuration->get('CONFIGURATION_INTEGRATION_TEST_FLAG');

        if ($flag === null) {
            // activate multistore
            $this->configuration->set('PS_MULTISHOP_FEATURE_ACTIVE', 1);

            // add a shop in existing group
            $shopGroup = $this->entityManager->find(ShopGroup::class, 1);
            $shop = new Shop();
            $shop->setActive(true);
            $shop->setIdCategory(2);
            $shop->setName('test_shop_2');
            $shop->setShopGroup($shopGroup);
            $shop->setColor('red');
            $shop->setThemeName('classic');
            $shop->setDeleted(false);

            $this->entityManager->persist($shop);
            $this->entityManager->flush();

            // activate flag
            $this->configuration->set('CONFIGURATION_INTEGRATION_TEST_FLAG', 1);
        }
    }
}
