<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A realistic menu so the storefront is demoable straight after setup.
 *
 * Idempotent (updateOrCreate on slug): running `db:seed` twice won't duplicate
 * rows. Images are hot-linked from Unsplash and were each checked to load and
 * to actually show the dish; the frontend shows a placeholder if one fails.
 *
 * Product shape:
 *   name, price, photo (Unsplash id), description,
 *   available (default true),
 *   offer => ['price' => 7.60, 'ends_in_days' => 3|null]
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->menu() as $categoryData) {
            $category = Category::updateOrCreate(
                ['slug' => Str::slug($categoryData['name'])],
                ['name' => $categoryData['name'], 'description' => $categoryData['description']],
            );

            foreach ($categoryData['products'] as $product) {
                $offer = $product['offer'] ?? null;

                Product::updateOrCreate(
                    ['slug' => Str::slug($product['name'])],
                    [
                        'category_id' => $category->id,
                        'name' => $product['name'],
                        'price' => $product['price'],
                        'description' => $product['description'],
                        'image_url' => "https://images.unsplash.com/photo-{$product['photo']}?w=800&q=80&auto=format&fit=crop",
                        'is_available' => $product['available'] ?? true,
                        'discount_price' => $offer['price'] ?? null,
                        'discount_ends_at' => isset($offer['ends_in_days'])
                            ? now()->addDays($offer['ends_in_days'])
                            : null,
                    ],
                );
            }
        }
    }

    /**
     * @return list<array{name: string, description: string, products: list<array<string, mixed>>}>
     */
    private function menu(): array
    {
        return [
            [
                'name' => 'Pizza',
                'description' => 'Wood-fired, hand-stretched sourdough bases.',
                'products' => [
                    [
                        'name' => 'Margherita',
                        'price' => 9.50,
                        'photo' => '1574071318508-1cdbab80d002',
                        'description' => 'San Marzano tomato, fior di latte mozzarella, fresh basil and extra-virgin olive oil.',
                        'offer' => ['price' => 7.60],
                    ],
                    [
                        'name' => 'Pepperoni',
                        'price' => 11.50,
                        'photo' => '1628840042765-356cda07504e',
                        'description' => 'Tomato, mozzarella and a generous layer of spicy pepperoni.',
                        'offer' => ['price' => 8.90, 'ends_in_days' => 20],
                    ],
                    ['name' => 'BBQ Chicken', 'price' => 12.50, 'photo' => '1565299624946-b28f40a0ae38', 'description' => 'Smoky BBQ sauce, roast chicken, red onion, mozzarella and fresh herbs.'],
                    ['name' => 'Quattro Formaggi', 'price' => 12.00, 'photo' => '1513104890138-7c749659a591', 'description' => 'Mozzarella, gorgonzola, fontina and parmesan with a drizzle of rosemary oil.'],
                    ['name' => 'Garden Veggie', 'price' => 10.50, 'photo' => '1585238342024-78d387f4a707', 'description' => 'Cherry tomatoes, black olives, basil and mozzarella on a crisp thin base.'],
                    [
                        'name' => 'Diavola',
                        'price' => 12.90,
                        'photo' => '1573821663912-6df460f9c684',
                        'description' => 'Spicy salami, jalapeños, chilli honey and mozzarella for a proper kick.',
                        'offer' => ['price' => 9.90],
                    ],
                    ['name' => 'Smoky Bacon Pizza', 'price' => 13.50, 'photo' => '1594007654729-407eedc4be65', 'description' => 'Smoked bacon, caramelised onion, mozzarella and a swirl of BBQ sauce.'],
                ],
            ],
            [
                'name' => 'Burgers',
                'description' => 'Brioche buns and 100% chuck patties, cooked to order.',
                'products' => [
                    [
                        'name' => 'Classic Cheeseburger',
                        'price' => 10.00,
                        'photo' => '1568901346375-23c9450c58cd',
                        'description' => 'Beef patty, cheddar, pickles, onion, lettuce and house burger sauce.',
                        'offer' => ['price' => 7.50, 'ends_in_days' => 25],
                    ],
                    [
                        'name' => 'Double Bacon Smash',
                        'price' => 13.50,
                        'photo' => '1553979459-d2229ba7433b',
                        'description' => 'Two smashed patties, crispy bacon, double American cheese and smoky mayo.',
                        'offer' => ['price' => 10.80, 'ends_in_days' => 14],
                    ],
                    ['name' => 'Crispy Chicken Burger', 'price' => 11.00, 'photo' => '1551782450-a2132b4ba21d', 'description' => 'Buttermilk fried chicken thigh, slaw, pickles and chipotle mayo.'],
                    // Seeded as unavailable on purpose: hidden from the storefront, visible to admins.
                    ['name' => 'Truffle Mushroom Burger', 'price' => 14.00, 'photo' => '1586190848861-99aa4a171e90', 'description' => 'Beef patty, sautéed mushrooms, Swiss cheese and truffle aioli. Back soon!', 'available' => false],
                    ['name' => 'Smokehouse Bacon Burger', 'price' => 13.90, 'photo' => '1550317138-10000687a72b', 'description' => 'Beef patty, streaky bacon, fried egg, cheddar and smoked chilli relish.'],
                    ['name' => 'Garden Deluxe Burger', 'price' => 11.90, 'photo' => '1571091655789-405eb7a3a3a8', 'description' => 'Beef patty, beef tomato, baby gem, red onion and garlic mayo.'],
                    [
                        'name' => 'Nashville Hot Chicken',
                        'price' => 12.90,
                        'photo' => '1606755962773-d324e0a13086',
                        'description' => 'Cayenne-spiced fried chicken, pickles and ranch in a toasted bun.',
                        'offer' => ['price' => 10.30],
                    ],
                ],
            ],
            [
                'name' => 'Pasta',
                'description' => 'Fresh pasta tossed to order.',
                'products' => [
                    [
                        'name' => 'Spaghetti Carbonara',
                        'price' => 12.00,
                        'photo' => '1612874742237-6526221588e3',
                        'description' => 'Guanciale, egg yolk, pecorino romano and cracked black pepper. No cream.',
                        'offer' => ['price' => 9.60, 'ends_in_days' => 21],
                    ],
                    [
                        'name' => 'Penne Arrabbiata',
                        'price' => 10.50,
                        'photo' => '1621996346565-e3dbc646d9a9',
                        'description' => 'Penne in a fiery tomato, garlic and chilli sauce with parsley.',
                        'offer' => ['price' => 7.90, 'ends_in_days' => 15],
                    ],
                    ['name' => 'Garlic Prawn Spaghetti', 'price' => 14.50, 'photo' => '1563379926898-05f4575a45d8', 'description' => 'King prawns, cherry tomatoes, garlic, chilli and white wine.'],
                    ['name' => 'Pesto Farfalle', 'price' => 11.00, 'photo' => '1473093295043-cdd812d0e601', 'description' => 'Basil and pine nut pesto, cherry tomatoes and shaved parmesan.'],
                ],
            ],
            [
                'name' => 'Wraps & Kebabs',
                'description' => 'Grilled to order, wrapped or on a plate.',
                'products' => [
                    [
                        'name' => 'Chicken Shawarma Box',
                        'price' => 12.50,
                        'photo' => '1529006557810-274b9b2fc783',
                        'description' => 'Marinated chicken, fries, pickles, garlic sauce and flatbread.',
                        'offer' => ['price' => 9.90, 'ends_in_days' => 28],
                    ],
                    [
                        'name' => 'Chicken Caesar Wrap',
                        'price' => 9.90,
                        'photo' => '1626700051175-6818013e1d4f',
                        'description' => 'Grilled chicken, romaine, parmesan and Caesar dressing in a soft tortilla.',
                        'offer' => ['price' => 7.40],
                    ],
                    [
                        'name' => 'Mixed Grill Kebab Plate',
                        'price' => 15.90,
                        'photo' => '1603360946369-dc9bb6258143',
                        'description' => 'Lamb and chicken skewers with fries, grilled tomato and onion salad.',
                        'offer' => ['price' => 12.70, 'ends_in_days' => 30],
                    ],
                    ['name' => 'Falafel Mezze Plate', 'price' => 11.50, 'photo' => '1561651823-34feb02250e4', 'description' => 'Crisp falafel, hummus, salad and warm pita. Vegetarian.'],
                    ['name' => 'Italian Sub', 'price' => 10.90, 'photo' => '1509722747041-616f39b57569', 'description' => 'Salami, provolone, tomato, rocket and oregano in a ciabatta roll.'],
                ],
            ],
            [
                'name' => 'Sides',
                'description' => 'The bits that make it a proper meal.',
                'products' => [
                    [
                        'name' => 'Loaded Cheese Fries',
                        'price' => 5.90,
                        'photo' => '1573080496219-bb080dd4f877',
                        'description' => 'Skin-on fries smothered in cheese sauce, spring onion and crispy onions.',
                        'offer' => ['price' => 4.40, 'ends_in_days' => 10],
                    ],
                    ['name' => 'Skin-on Fries', 'price' => 3.50, 'photo' => '1541592106381-b31e9677c0e5', 'description' => 'Golden fries with sea salt.'],
                    ['name' => 'Crispy Chicken Bites', 'price' => 6.50, 'photo' => '1619221882220-947b3d3c8861', 'description' => 'Bite-sized crispy chicken with a sweet chilli dip.'],
                    [
                        'name' => 'Buffalo Wings',
                        'price' => 7.50,
                        'photo' => '1608039755401-742074f0548d',
                        'description' => 'Six wings tossed in buffalo sauce with blue cheese dip.',
                        'offer' => ['price' => 5.50, 'ends_in_days' => 12],
                    ],
                ],
            ],
            [
                'name' => 'Salads & Bowls',
                'description' => 'Fresh, filling and made daily.',
                'products' => [
                    ['name' => 'Chicken Caesar Salad', 'price' => 9.50, 'photo' => '1546793665-c74683f339c1', 'description' => 'Grilled chicken, romaine, parmesan, sourdough croutons and Caesar dressing.'],
                    [
                        'name' => 'Rainbow Buddha Bowl',
                        'price' => 10.50,
                        'photo' => '1512621776951-a57141f2eefd',
                        'description' => 'Avocado, chickpeas, roasted sweet potato, red cabbage and tahini dressing.',
                        'offer' => ['price' => 8.40, 'ends_in_days' => 24],
                    ],
                    ['name' => 'Garden Salad', 'price' => 7.50, 'photo' => '1540420773420-3366772f4999', 'description' => 'Mixed leaves, radish, cucumber, carrot and a lemon vinaigrette.'],
                ],
            ],
            [
                'name' => 'Breakfast',
                'description' => 'Served until noon, every day.',
                'products' => [
                    ['name' => 'Avocado & Poached Egg Toast', 'price' => 8.50, 'photo' => '1525351484163-7529414344d8', 'description' => 'Smashed avocado, poached egg and chilli flakes on toasted sourdough.'],
                    ['name' => 'Fried Egg Sourdough', 'price' => 6.90, 'photo' => '1533089860892-a7c6f0a88666', 'description' => 'Two fried eggs on buttered sourdough with grilled tomato.'],
                    [
                        'name' => 'Berry French Toast',
                        'price' => 7.90,
                        'photo' => '1484723091739-30a097e8f929',
                        'description' => 'Thick-cut brioche with blueberries, banana and maple syrup.',
                        'offer' => ['price' => 5.90, 'ends_in_days' => 18],
                    ],
                    [
                        'name' => 'Blueberry Pancakes',
                        'price' => 7.50,
                        'photo' => '1528207776546-365bb710ee93',
                        'description' => 'A stack of fluffy pancakes with blueberries and butter.',
                        'offer' => ['price' => 5.60, 'ends_in_days' => 16],
                    ],
                    ['name' => 'Maple Pancake Stack', 'price' => 7.90, 'photo' => '1567620905732-2d1ec7ab7445', 'description' => 'Buttermilk pancakes with banana and a generous pour of maple syrup.'],
                    ['name' => 'Fresh Fruit Bowl', 'price' => 6.50, 'photo' => '1490474418585-ba9bad8fd0ea', 'description' => 'Seasonal fruit, berries and a pot of yoghurt. Vegetarian.'],
                ],
            ],
            [
                'name' => 'Desserts',
                'description' => 'Something sweet to finish.',
                'products' => [
                    [
                        'name' => 'Tiramisu',
                        'price' => 6.50,
                        'photo' => '1571877227200-a0d98ea607e9',
                        'description' => 'Espresso-soaked savoiardi layered with mascarpone and cocoa.',
                        'offer' => ['price' => 4.90],
                    ],
                    ['name' => 'Chocolate Fudge Brownie', 'price' => 5.50, 'photo' => '1606313564200-e75d5e30476c', 'description' => 'Warm, gooey dark chocolate brownie with a fudge drizzle.'],
                    ['name' => 'Strawberry Panna Cotta', 'price' => 6.00, 'photo' => '1488477181946-6428a0291777', 'description' => 'Vanilla bean panna cotta topped with macerated strawberries.'],
                    ['name' => 'Cookies & Cream Sundae', 'price' => 6.50, 'photo' => '1563805042-7684c019e1cb', 'description' => 'Vanilla ice cream, crushed cookies, whipped cream and chocolate sauce.'],
                    [
                        'name' => 'Chocolate Fudge Cake',
                        'price' => 6.90,
                        'photo' => '1578985545062-69928b1d9587',
                        'description' => 'A tall slice of chocolate cake with fudge frosting.',
                        'offer' => ['price' => 4.90, 'ends_in_days' => 12],
                    ],
                    ['name' => 'Raspberry Cream Cake', 'price' => 6.90, 'photo' => '1565958011703-44f9829ba187', 'description' => 'Light sponge layered with cream and fresh raspberries.'],
                    ['name' => 'Berry Crepes', 'price' => 6.50, 'photo' => '1587314168485-3236d6710814', 'description' => 'Thin crepes with whipped cream, strawberries and raspberries.'],
                    ['name' => 'Chocolate Chip Cookies', 'price' => 4.50, 'photo' => '1558961363-fa8fdf82db35', 'description' => 'Four soft-baked cookies, still warm from the oven.'],
                ],
            ],
            [
                'name' => 'Drinks',
                'description' => 'Fresh juices, lemonades and coffee.',
                'products' => [
                    [
                        'name' => 'Fresh Orange Juice',
                        'price' => 3.90,
                        'photo' => '1600271886742-f049cd451bba',
                        'description' => 'Freshly squeezed oranges, nothing else.',
                        'offer' => ['price' => 2.90],
                    ],
                    ['name' => 'Mint Lemonade', 'price' => 3.50, 'photo' => '1621263764928-df1444c5e859', 'description' => 'House-made lemonade with fresh mint and lime.'],
                    [
                        'name' => 'Iced Latte',
                        'price' => 4.20,
                        'photo' => '1461023058943-07fcbe16d735',
                        'description' => 'Double espresso over ice with cold milk.',
                        'offer' => ['price' => 3.20, 'ends_in_days' => 22],
                    ],
                    ['name' => 'Strawberry Lime Cooler', 'price' => 4.50, 'photo' => '1497534446932-c925b458314e', 'description' => 'Muddled strawberries, lime and sparkling water.'],
                ],
            ],
        ];
    }
}
