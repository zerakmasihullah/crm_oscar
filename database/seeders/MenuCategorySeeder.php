<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define all menu categories
        $categories = [
            'Highlights',
            'Chefs Specialities',
            'Light Bites',
            'Pizza',
            'Buffalo Wings',
            'Chicken Baps',
            'Angus Beef Burgers',
            'Hot Sandwiches',
            'Fresh Fish',
            'Pasta',
            'Sauce / Dips',
            'Side Orders',
            'Veggie Delights',
            'Kids Menu',
            'Dessert',
            'Minerals',
            'Wine & Beers',
            'Escalope Of Chicken',
        ];

        // Create menu categories with images
        $categoryImages = [
            'Highlights' => 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=800&h=600&fit=crop',
            'Chefs Specialities' => 'https://images.unsplash.com/photo-1558030006-450675393462?w=800&h=600&fit=crop',
            'Light Bites' => 'https://images.unsplash.com/photo-1547592166-23ac45744acd?w=800&h=600&fit=crop',
            'Pizza' => 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=800&h=600&fit=crop',
            'Buffalo Wings' => 'https://images.unsplash.com/photo-1527477396000-e27137b3950f?w=800&h=600&fit=crop',
            'Chicken Baps' => 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=800&h=600&fit=crop',
            'Angus Beef Burgers' => 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=800&h=600&fit=crop',
            'Hot Sandwiches' => 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=800&h=600&fit=crop',
            'Fresh Fish' => 'https://images.unsplash.com/photo-1544943910-4c1dc44aab44?w=800&h=600&fit=crop',
            'Pasta' => 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?w=800&h=600&fit=crop',
            'Sauce / Dips' => 'https://images.unsplash.com/photo-1606914509765-5c0ace8a0c28?w=800&h=600&fit=crop',
            'Side Orders' => 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=800&h=600&fit=crop',
            'Veggie Delights' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=800&h=600&fit=crop',
            'Kids Menu' => 'https://images.unsplash.com/photo-1556910103-1c02745aae4d?w=800&h=600&fit=crop',
            'Dessert' => 'https://images.unsplash.com/photo-1551024506-0bccd828d307?w=800&h=600&fit=crop',
            'Minerals' => 'https://images.unsplash.com/photo-1523362628745-0c100150b504?w=800&h=600&fit=crop',
            'Wine & Beers' => 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?w=800&h=600&fit=crop',
            'Escalope Of Chicken' => 'https://images.unsplash.com/photo-1604503468506-a8da13d82791?w=800&h=600&fit=crop',
        ];

        $menuMap = [];
        foreach ($categories as $categoryTitle) {
            $menu = Menu::firstOrCreate(
                ['title' => $categoryTitle],
                [
                    'description' => null,
                    'image' => $categoryImages[$categoryTitle] ?? null,
                ]
            );
            $menuMap[$categoryTitle] = $menu;
        }

        // Chefs Specialities (5 items)
        $this->createMenuItem($menuMap['Chefs Specialities'], 'Roast Joint Of The Day', 14.50, 'Served with Veg, Potato & lashings of Gravy', true, 'https://images.unsplash.com/photo-1546833999-b9f581a1996d?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Chefs Specialities'], 'Goujons Of Chicken', 13.00, 'Strips of breaded chicken, deep fried with a cajun dip, fries', true, 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Chefs Specialities'], 'Louisiana Hot Goujons', 13.50, 'As our goujons, but soaked in Louisiana Hot sauce', true, 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Chefs Specialities'], 'Pulled Pork Bap', 15.00, '10 hour slow cooked BBQ pork, served in a bap with salad & onion rings served with home cooked fries', true, 'https://images.unsplash.com/photo-1528607929212-4d2970e26e2d?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Chefs Specialities'], 'Chicken Curry', 15.00, 'Chicken breast in a mild Curry sauce. Served with chips and rice', true, 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?w=800&h=600&fit=crop');

        // Light Bites (4 items)
        $this->createMenuItem($menuMap['Light Bites'], 'Homemade Vegetable Soup', 5.00, 'Served with bread', true, 'https://images.unsplash.com/photo-1547592166-23ac45744acd?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Light Bites'], 'Garlic Mushrooms', 8.50, 'Button mushrooms breaded and deep fried until golden brown served with garlic mayo', true, 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Light Bites'], 'Battered Onion Rings', 5.90, 'tasty cripsy onion rings, golden fried and served with a cajun dip', true, 'https://images.unsplash.com/photo-1615367423057-4b3cbcb0dbf2?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Light Bites'], 'Chicken & Bacon Vol Au Vent', 13.50, 'Chicken in a creamy white wine sauce with bacon and served in a puff pastry case', true, 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=800&h=600&fit=crop');

        // Pizza (4 items)
        $this->createMenuItem($menuMap['Pizza'], 'Margherita Pizza', 12.00, '12" Stone baked base topped with Tomato Pizza Sauce & Mozzerella', true, 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Pizza'], 'Meat Feast Pizza', 14.00, '12" Stone baked base topped with Tomato Pizza Sauce, Mozzerella, Pepperoni, Ham & Spicy Beef', true, 'https://images.unsplash.com/photo-1628840042765-356cda07504e?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Pizza'], 'Chicken Fajita Pizza', 14.00, '12" Stone baked base topped with Tomato Pizza Sauce, Mozzerella, Chicken Breast, Fajita Spices, Onion & Peppers', true, 'https://images.unsplash.com/photo-1571997478779-2adcbbe9ab2f?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Pizza'], 'Pepperoni Passion Pizza', 14.00, '12" Stone baked base topped with Tomato Pizza Sauce, Mozzerella & Pepperoni', true, 'https://images.unsplash.com/photo-1628840042765-356cda07504e?w=800&h=600&fit=crop');

        // Buffalo Wings (2 items)
        $this->createMenuItem($menuMap['Buffalo Wings'], 'Louisiana Hot Sauce Wings', 13.50, 'Our famous fresh cooked wings in Louisiana Hot sauce', true, 'https://images.unsplash.com/photo-1527477396000-e27137b3950f?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Buffalo Wings'], 'Sticky BBQ Sauce wings', 13.50, 'Our famous fresh cooked wings in sticky BBQ sauce', true, 'https://images.unsplash.com/photo-1527477396000-e27137b3950f?w=800&h=600&fit=crop');

        // Chicken Baps (1 item)
        $this->createMenuItem($menuMap['Chicken Baps'], 'Its Not Nandos', 15.00, 'Char Grilled Succulent Chicken Breast, served in Toasted Bap with Fries. Chicken Breast, Spicy Piri Piri Sauce with Slaw, Lettuce, Tomato, Onion Ring & Jalapeno', true, 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=800&h=600&fit=crop');

        // Angus Beef Burgers (5 items)
        $this->createMenuItem($menuMap['Angus Beef Burgers'], 'Naked Burger', 11.00, 'Bun, burger & fries and thats it!!', true, 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Angus Beef Burgers'], 'It\'s Not Nandos Beef Burger', 15.00, 'Spicy Piri Piri Sauce, nacho cheese, Onion Ring.', true, 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Angus Beef Burgers'], 'Oskars Gourmet Burger', 15.00, 'topped with lettuce, tomato, Onion Ring, Coleslaw, burger sauce in a flour bap', true, 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Angus Beef Burgers'], 'Miss Piggy', 16.00, 'topped with 10 Hour Slow Cooked BBQ Pulled Pork, Onion Ring& BBQ Sauce', true, 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Angus Beef Burgers'], 'Big Mouth', null, 'Double your burger to One Pound of Meat - any topping above', false, 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=800&h=600&fit=crop');

        // Pasta (5 items)
        $this->createMenuItem($menuMap['Pasta'], 'Beef Lasagne', 13.00, 'Layers of pasta and bolognaise, topped with cheese sauce served with fries and slaw', true, 'https://images.unsplash.com/photo-1574894709920-11b28e7367e3?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Pasta'], 'Pasta Carbonara', 13.50, 'Pasta in a creamy bacon sauce served with garlic bread', true, 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Pasta'], 'Cajun Chicken Pasta', 13.50, 'Pasta in a creamy sauce with spcied cajun chicken served with garlic bread', true, 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Pasta'], 'Creamy Chicken Pasta', 13.00, 'Pasta in a creamy white wine sauce and chicken breast', true, 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Pasta'], 'Add Garlic Bread', 4.00, null, true, 'https://images.unsplash.com/photo-1573140247632-f8fd74997d5c?w=800&h=600&fit=crop');

        // Sauce / Dips (3 items)
        $this->createMenuItem($menuMap['Sauce / Dips'], 'Dips', 0.95, null, true, 'https://images.unsplash.com/photo-1606914509765-5c0ace8a0c28?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Sauce / Dips'], 'Portion of Gravy', 2.00, null, true, 'https://images.unsplash.com/photo-1606914509765-5c0ace8a0c28?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Sauce / Dips'], 'Portion of Pepper Sauce', 2.00, null, true, 'https://images.unsplash.com/photo-1606914509765-5c0ace8a0c28?w=800&h=600&fit=crop');

        // Side Orders (5 items)
        $this->createMenuItem($menuMap['Side Orders'], 'Garlic Bread', 4.00, null, true, 'https://images.unsplash.com/photo-1573140247632-f8fd74997d5c?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Side Orders'], 'Garlic Bread with Cheese', 4.50, null, true, 'https://images.unsplash.com/photo-1573140247632-f8fd74997d5c?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Side Orders'], 'Homemade Coleslaw', 2.00, null, true, 'https://images.unsplash.com/photo-1606914509765-5c0ace8a0c28?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Side Orders'], 'Sweet Potato Fries', 4.00, null, true, 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Side Orders'], 'Homecooked Chips', 3.50, null, true, 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=800&h=600&fit=crop');

        // Veggie Delights (3 items)
        $this->createMenuItem($menuMap['Veggie Delights'], 'Vegetarian Panini', 11.00, 'Mozzerealla,  tomato, red onion, pesto & fries', true, 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Veggie Delights'], 'All Plant Cajun Goujons', 12.00, 'Served with salad, fries and cajun dip', true, 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Veggie Delights'], 'Veggie Pizza', 12.00, 'Pizza Sauce or Piri Piri Sauce, mozzarella, roast pepers and red onion', true, 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=800&h=600&fit=crop');

        // Wine & Beers (13 items)
        $this->createMenuItem($menuMap['Wine & Beers'], '1/4 Bottle of Prosecco', 8.90, 'age restriction 18 plus, ABV 12%', true, 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Wine & Beers'], '1/4 Bottle of Pinot Grigio', 7.00, 'age restriction 18 plus, ABV 12%', true, 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Wine & Beers'], '1/4 Bottle of Sauvignon Blanc', 7.00, 'age restriction 18 plus, ABV 12%', true, 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Wine & Beers'], '1/4 Bottle of Merlot', 7.00, 'age restriction 18 plus, ABV 12%', true, 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Wine & Beers'], '1/4 Bottle of Shiraz', 7.00, 'age restriction 18 plus, ABV 12%', true, 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Wine & Beers'], 'Large Pinot Grigio', 25.00, 'age restriction 18 plus, ABV 12%', true, 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Wine & Beers'], 'Large Cabernet Sauvignon', 25.00, 'age restriction 18 plus, ABV 12%', true, 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Wine & Beers'], 'Large Sauvignon Blanc', 26.00, null, true, 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Wine & Beers'], '4 x Corona', 20.00, 'age restriction 18 plus, 4 bottles 330ml Corona Extra', true, 'https://images.unsplash.com/photo-1608270586620-248524c67de9?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Wine & Beers'], 'O\'haras Pale Ale', 7.00, 'age restriction 18 plus, Dry hopped I P A 5.2 %', true, 'https://images.unsplash.com/photo-1608270586620-248524c67de9?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Wine & Beers'], 'O\' Haras Tropical Ipa', 7.00, 'age restriction 18 plus, Mango, tangerine, stone fruits 7.2%', true, 'https://images.unsplash.com/photo-1608270586620-248524c67de9?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Wine & Beers'], 'O\' Haras Red Ale', 7.00, 'age restriction 18 plus, Traditional Red Ale 4.3%', true, 'https://images.unsplash.com/photo-1608270586620-248524c67de9?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Wine & Beers'], 'O\' Haras Lager', 7.00, 'age restriction 18 plus, Premium craft lager 4.5%', true, 'https://images.unsplash.com/photo-1608270586620-248524c67de9?w=800&h=600&fit=crop');

        // Escalope Of Chicken (3 items)
        $this->createMenuItem($menuMap['Escalope Of Chicken'], 'Chicken With Mushroom', null, 'Breaded chicken fillet topped with a creamy Mushroom Sauce served with chips', false, 'https://images.unsplash.com/photo-1604503468506-a8da13d82791?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Escalope Of Chicken'], 'Chicken, Pepper Sauce', null, 'Breaded chicken fillet topped with homemade cracked black pepper sauce served with chips', false, 'https://images.unsplash.com/photo-1604503468506-a8da13d82791?w=800&h=600&fit=crop');
        $this->createMenuItem($menuMap['Escalope Of Chicken'], 'Chicken Kiev', null, 'Breaded chicken fillet topped with homemade garlic butter served with chips', false, 'https://images.unsplash.com/photo-1604503468506-a8da13d82791?w=800&h=600&fit=crop');
    }

    /**
     * Helper method to create menu items
     */
    private function createMenuItem($menu, $title, $price, $description = null, $status = true, $image = null)
    {
        MenuItem::firstOrCreate(
            [
                'title' => $title,
                'menu_id' => $menu->id,
            ],
            [
                'description' => $description,
                'image' => $image,
                'price' => $price,
                'status' => $status,
            ]
        );
    }
}
