<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A realistic menu so the storefront is demoable straight after setup.
 *
 * Idempotent (updateOrCreate on slug): running `db:seed` twice won't duplicate rows.
 * Images are hot-linked from Unsplash; the frontend shows a placeholder if one fails.
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

            foreach ($categoryData['products'] as [$name, $price, $photo, $description, $available]) {
                Product::updateOrCreate(
                    ['slug' => Str::slug($name)],
                    [
                        'category_id' => $category->id,
                        'name' => $name,
                        'price' => $price,
                        'description' => $description,
                        'image_url' => "https://images.unsplash.com/photo-{$photo}?w=800&q=80&auto=format&fit=crop",
                        'is_available' => $available,
                    ],
                );
            }
        }
    }

    /**
     * @return list<array{name: string, description: string, products: list<array{string, float, string, string, bool}>}>
     */
    private function menu(): array
    {
        return [
            [
                'name' => 'Pizza',
                'description' => 'Wood-fired, hand-stretched sourdough bases.',
                'products' => [
                    ['Margherita', 9.50, '1574071318508-1cdbab80d002', 'San Marzano tomato, fior di latte mozzarella, fresh basil and extra-virgin olive oil.', true],
                    ['Pepperoni', 11.50, '1628840042765-356cda07504e', 'Tomato, mozzarella and a generous layer of spicy pepperoni.', true],
                    ['BBQ Chicken', 12.50, '1565299624946-b28f40a0ae38', 'Smoky BBQ sauce, roast chicken, red onion, mozzarella and fresh herbs.', true],
                    ['Quattro Formaggi', 12.00, '1513104890138-7c749659a591', 'Mozzarella, gorgonzola, fontina and parmesan with a drizzle of rosemary oil.', true],
                    ['Garden Veggie', 10.50, '1585238342024-78d387f4a707', 'Cherry tomatoes, black olives, basil and mozzarella on a crisp thin base.', true],
                ],
            ],
            [
                'name' => 'Burgers',
                'description' => 'Brioche buns and 100% chuck patties, cooked to order.',
                'products' => [
                    ['Classic Cheeseburger', 10.00, '1568901346375-23c9450c58cd', 'Beef patty, cheddar, pickles, onion, lettuce and house burger sauce.', true],
                    ['Double Bacon Smash', 13.50, '1553979459-d2229ba7433b', 'Two smashed patties, crispy bacon, double American cheese and smoky mayo.', true],
                    ['Crispy Chicken Burger', 11.00, '1551782450-a2132b4ba21d', 'Buttermilk fried chicken thigh, slaw, pickles and chipotle mayo.', true],
                    // Seeded as unavailable on purpose: hidden from the storefront, visible to admins.
                    ['Truffle Mushroom Burger', 14.00, '1586190848861-99aa4a171e90', 'Beef patty, sautéed mushrooms, Swiss cheese and truffle aioli. Back soon!', false],
                ],
            ],
            [
                'name' => 'Pasta',
                'description' => 'Fresh pasta tossed to order.',
                'products' => [
                    ['Spaghetti Carbonara', 12.00, '1612874742237-6526221588e3', 'Guanciale, egg yolk, pecorino romano and cracked black pepper. No cream.', true],
                    ['Penne Arrabbiata', 10.50, '1621996346565-e3dbc646d9a9', 'Penne in a fiery tomato, garlic and chilli sauce with parsley.', true],
                    ['Garlic Prawn Spaghetti', 14.50, '1563379926898-05f4575a45d8', 'King prawns, cherry tomatoes, garlic, chilli and white wine.', true],
                    ['Pesto Farfalle', 11.00, '1473093295043-cdd812d0e601', 'Basil and pine nut pesto, cherry tomatoes and shaved parmesan.', true],
                ],
            ],
            [
                'name' => 'Salads & Bowls',
                'description' => 'Fresh, filling and made daily.',
                'products' => [
                    ['Chicken Caesar Salad', 9.50, '1546793665-c74683f339c1', 'Grilled chicken, romaine, parmesan, sourdough croutons and Caesar dressing.', true],
                    ['Rainbow Buddha Bowl', 10.50, '1512621776951-a57141f2eefd', 'Avocado, chickpeas, roasted sweet potato, red cabbage and tahini dressing.', true],
                    ['Garden Salad', 7.50, '1540420773420-3366772f4999', 'Mixed leaves, radish, cucumber, carrot and a lemon vinaigrette.', true],
                ],
            ],
            [
                'name' => 'Desserts',
                'description' => 'Something sweet to finish.',
                'products' => [
                    ['Tiramisu', 6.50, '1571877227200-a0d98ea607e9', 'Espresso-soaked savoiardi layered with mascarpone and cocoa.', true],
                    ['Chocolate Fudge Brownie', 5.50, '1606313564200-e75d5e30476c', 'Warm, gooey dark chocolate brownie with a fudge drizzle.', true],
                    ['Strawberry Panna Cotta', 6.00, '1488477181946-6428a0291777', 'Vanilla bean panna cotta topped with macerated strawberries.', true],
                    ['Cookies & Cream Sundae', 6.50, '1563805042-7684c019e1cb', 'Vanilla ice cream, crushed cookies, whipped cream and chocolate sauce.', true],
                ],
            ],
            [
                'name' => 'Drinks',
                'description' => 'Fresh juices, lemonades and coffee.',
                'products' => [
                    ['Fresh Orange Juice', 3.90, '1600271886742-f049cd451bba', 'Freshly squeezed oranges, nothing else.', true],
                    ['Mint Lemonade', 3.50, '1621263764928-df1444c5e859', 'House-made lemonade with fresh mint and lime.', true],
                    ['Iced Latte', 4.20, '1461023058943-07fcbe16d735', 'Double espresso over ice with cold milk.', true],
                    ['Strawberry Lime Cooler', 4.50, '1497534446932-c925b458314e', 'Muddled strawberries, lime and sparkling water.', true],
                ],
            ],
        ];
    }
}
