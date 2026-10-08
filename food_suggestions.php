<?php
// food_suggestions.php - Condition-Based Food & Nutrition Guidance
require_once 'config.php';

$patient = null;
if (isset($_SESSION['patient_id'])) {
    $patient = getPatientById($pdo, $_SESSION['patient_id']);
}

// Auto-detect default condition from latest assessment if available
$latest_ass = $patient ? getLatestAssessment($pdo, $patient['id']) : null;
$default_condition = 'osteoarthritis';

if ($latest_ass) {
    $part = strtolower($latest_ass['body_part'] ?? '');
    $classif = strtolower($latest_ass['classification'] ?? '');
    if (strpos($part, 'knee') !== false || strpos($part, 'hip') !== false) {
        $default_condition = ($classif === 'chronic') ? 'osteoarthritis' : 'ligament';
    } elseif (strpos($part, 'back') !== false || strpos($part, 'neck') !== false) {
        $default_condition = 'spondylosis';
    } elseif ($classif === 'chronic') {
        $default_condition = 'osteoporosis';
    }
}

$selected_condition = $_GET['condition'] ?? $default_condition;
$valid_conditions = ['osteoarthritis', 'osteoporosis', 'fracture', 'ligament', 'gout', 'spondylosis'];
if (!in_array($selected_condition, $valid_conditions)) {
    $selected_condition = 'osteoarthritis';
}

$diet_filter = $_GET['diet'] ?? 'all'; // 'all', 'veg', 'nonveg'

// Clinical Condition Database with localized nutritional data
$nutrition_data = [
    'osteoarthritis' => [
        'name' => t('cond_osteoarthritis'),
        'icon' => '🦴',
        'badge' => 'Joint Cartilage & Anti-Inflammatory',
        'overview_en' => 'Osteoarthritis involves progressive cartilage wear and joint inflammation. Anti-inflammatory foods, rich in Omega-3 fatty acids, polyphenols, and antioxidants, protect remaining cartilage, decrease joint stiffness, and soothe systemic pain.',
        'overview_kn' => 'ಆಸ್ಟಿಯೊಆರ್ಥ್ರೈಟಿಸ್ ಕೀಲುಗಳ ಮೃದ್ವಸ್ಥಿ ಸವೆತ ಮತ್ತು ಊತವನ್ನು ಒಳಗೊಂಡಿರುತ್ತದೆ. ಒಮೆಗಾ-3 ಕೊಬ್ಬಿನಾಮ್ಲಗಳು ಮತ್ತು ಉತ್ಕರ್ಷಣ ನಿರೋಧಕಗಳು ಕೀಲುಗಳ ಬಿಗಿತ ಮತ್ತು ನೋವನ್ನು ಕಡಿಮೆ ಮಾಡಲು ಸಹಾಯ ಮಾಡುತ್ತವೆ.',
        'overview_hi' => 'ऑस्टियोआर्थराइटिस में जोड़ों के कार्टिलेज का घिसाव और सूजन होती है। ओमेगा-3, एंटी-ऑक्सीडेंट और सूजन-रोधी आहार जोड़ों की जकड़न और दर्द को शांत करने में सहायक होते हैं।',
        'hydration' => '2.5 - 3 Litres / day (Keeps synovial fluid viscous and joint lubricated)',
        'superfoods' => [
            ['name' => 'Turmeric / Curcumin Milk (Golden Milk)', 'kannada' => 'ಅರಿಶಿನ ಹಾಲು', 'hindi' => 'हल्दी वाला दूध', 'type' => 'veg', 'nutrient' => 'Curcumin', 'benefit' => 'Potent natural anti-inflammatory agent comparable to low-dose NSAIDs.'],
            ['name' => 'Walnuts & Chia / Flax Seeds', 'kannada' => 'ಅಕ್ರೋಟ್ ಮತ್ತು ಅಗಸೆ ಬೀಜಗಳು', 'hindi' => 'अखरोट और अलसी के बीज', 'type' => 'veg', 'nutrient' => 'Plant Omega-3 (ALA)', 'benefit' => 'Suppresses cartilage-degrading inflammatory cytokines.'],
            ['name' => 'Wild Fatty Fish (Salmon / Mackerel / Sardines)', 'kannada' => 'ಸಾಲ್ಮನ್ / ಬಂಗುಡೆ ಮೀನು', 'hindi' => 'सैल्मन / टूना मछली', 'type' => 'nonveg', 'nutrient' => 'EPA & DHA Omega-3', 'benefit' => 'Reduces joint swelling, morning stiffness, and pain.'],
            ['name' => 'Dark Leafy Greens (Spinach, Kale, Methi)', 'kannada' => 'ಪಾಲಕ್ ಮತ್ತು ಮೆಂತ್ಯ ಸೊಪ್ಪು', 'hindi' => 'पालक, मेथी और हरी सब्जियां', 'type' => 'veg', 'nutrient' => 'Vitamin K & Calcium', 'benefit' => 'Protects subchondral bone integrity and joint tissues.'],
            ['name' => 'Berries & Pomegranates', 'kannada' => 'ದಾಳಿಂಬೆ ಮತ್ತು ಬೆರ್ರಿ ಹಣ್ಣುಗಳು', 'hindi' => 'अनार और जामुन/बेरी', 'type' => 'veg', 'nutrient' => 'Anthocyanins', 'benefit' => 'Neutralizes oxidative free radicals that damage joint cartilage.'],
            ['name' => 'Extra Virgin Olive Oil & Mustard Oil', 'kannada' => 'ಆಲಿವ್ ಎಣ್ಣೆ / ಸಾಸಿವೆ ಎಣ್ಣೆ', 'hindi' => 'जैतून का तेल / सरसों तेल', 'type' => 'veg', 'nutrient' => 'Oleocanthal', 'benefit' => 'Inhibits COX-1 and COX-2 inflammatory pathways naturally.']
        ],
        'avoid' => [
            ['name' => 'Ultra-processed Sugars & Sweets', 'reason' => 'Triggers blood sugar spikes and pro-inflammatory cytokine release.'],
            ['name' => 'Fried Foods & Vanaspati / Trans Fats', 'reason' => 'Accelerates systemic joint inflammation and increases pain sensitivity.'],
            ['name' => 'Refined Flour (Maida) & Bakery Items', 'reason' => 'High glycemic index promotes chronic inflammatory flare-ups.'],
            ['name' => 'Excessive Red Meat', 'reason' => 'High arachidonic acid content exacerbates joint swelling.']
        ],
        'meal_plan' => [
            'breakfast' => 'Oatmeal with crushed walnuts, chia seeds, sliced papaya, and green tea.',
            'lunch' => 'Brown rice / Roti, Methi Dal, Steamed broccoli / Palak subji, and bowl of curd (probiotics).',
            'evening' => 'Roasted pumpkin seeds, hand of almonds with warm ginger-cinnamon tea.',
            'dinner' => 'Light vegetable soup, Grilled fish / Tofu with sauteed vegetables, followed by a cup of warm turmeric milk.'
        ]
    ],

    'osteoporosis' => [
        'name' => t('cond_osteoporosis'),
        'icon' => '🥛',
        'badge' => 'Bone Mineral Density & Calcium',
        'overview_en' => 'Osteoporosis causes micro-architectural deterioration of bone tissue, making bones fragile. Nutrition must focus on bioavailable Calcium, Vitamin D3 for intestinal absorption, Vitamin K2 to deposit calcium into bone matrix, and Magnesium.',
        'overview_kn' => 'ಆಸ್ಟಿಯೊಪೊರೋಸಿಸ್ ಮೂಳೆಗಳು ದುರ್ಬಲಗೊಳ್ಳಲು ಕಾರಣವಾಗುತ್ತದೆ. ಕ್ಯಾಲ್ಸಿಯಂ, ವಿಟಮಿನ್ ಡಿ3, ಮತ್ತು ಮೆಗ್ನೀಸಿಯಮ್ ಸಮೃದ್ಧ ಆಹಾರಗಳು ಮೂಳೆಯ ಸಾಂದ್ರತೆಯನ್ನು ಹೆಚ್ಚಿಸುತ್ತವೆ.',
        'overview_hi' => 'ऑस्टियोपोरोसिस हड्डियों को कमजोर और खोखला बनाता है। कैल्शियम, विटामिन डी3 और मैग्नीशियम युक्त आहार हड्डियों को मजबूत रखने के लिए अनिवार्य हैं।',
        'hydration' => '2.5 Litres / day (Ensure adequate mineral water; avoid excessive caffeinated diuretics)',
        'superfoods' => [
            ['name' => 'Finger Millet (Ragi / Nachni)', 'kannada' => 'ರಾಗಿ (ಮುದ್ದೆ / ಗಂಜಿ)', 'hindi' => 'रागी / मडुआ', 'type' => 'veg', 'nutrient' => 'Calcium (344mg/100g)', 'benefit' => 'Highest plant-based calcium content among all whole grains.'],
            ['name' => 'Sesame Seeds (Til / Ellu)', 'kannada' => 'ಬಿಳಿ / ಕಪ್ಪು ಎಳ್ಳು', 'hindi' => 'सफेद / काले तिल', 'type' => 'veg', 'nutrient' => 'Calcium & Zinc', 'benefit' => 'One tablespoon provides nearly 90mg of readily absorbed calcium.'],
            ['name' => 'Milk, Yogurt (Curd) & Paneer', 'kannada' => 'ಹಾಲು, ಮೊಸರು ಮತ್ತು ಪನೀರ್', 'hindi' => 'दूध, दही और पनीर', 'type' => 'veg', 'nutrient' => 'Calcium & Vit D3', 'benefit' => 'Classic bio-available calcium for bone mineralization.'],
            ['name' => 'Moringa / Drumstick Leaves', 'kannada' => 'ನುಗ್ಗೆ ಸೊಪ್ಪು', 'hindi' => 'सहजन / मोरिंगा के पत्ते', 'type' => 'veg', 'nutrient' => 'Calcium, Magnesium, Vit C', 'benefit' => 'Powerhouse for bone rebuilding with 4x calcium of milk.'],
            ['name' => 'Eggs (with yolk)', 'kannada' => 'ಮೊಟ್ಟೆ (ಹಳದಿ ಲೋಳೆ ಸಹಿತ)', 'hindi' => 'अंडे (जर्दी सहित)', 'type' => 'nonveg', 'nutrient' => 'Vitamin D3 & Protein', 'benefit' => 'Supports gut absorption of dietary calcium into the bloodstream.'],
            ['name' => 'Fortified Soya & Almonds', 'kannada' => 'ಸೋಯಾ ಮತ್ತು ಬಾದಾಮಿ', 'hindi' => 'सोयाबीन और बादाम', 'type' => 'veg', 'nutrient' => 'Isoflavones & Magnesium', 'benefit' => 'Slows bone resorption rate in post-menopausal stages.']
        ],
        'avoid' => [
            ['name' => 'Carbonated Soft Drinks & Colas', 'reason' => 'High phosphoric acid leaches calcium from skeletal bones into urine.'],
            ['name' => 'Excessive Salt & Salty Snacks', 'reason' => 'High sodium causes the kidneys to excrete dietary calcium.'],
            ['name' => 'Heavy Caffeine (>3 cups coffee/day)', 'reason' => 'Interferes with intestinal calcium absorption.'],
            ['name' => 'Alcohol & Smoking', 'reason' => 'Directly inhibits osteoblast (bone-building cells) activity.']
        ],
        'meal_plan' => [
            'breakfast' => 'Ragi malt / porridge or paneer stuffed paratha with a glass of warm milk.',
            'lunch' => 'Ragi mudde or whole wheat rotis, Drumstick/Moringa sambar, raw sprouted moong salad, and thick curd.',
            'evening' => 'Til (sesame) laddu or 6 soaked almonds with warm green tea.',
            'dinner' => 'Dal khichdi or vegetable dahlia, stir-fried broccoli & mushrooms, and a boiled egg.'
        ]
    ],

    'fracture' => [
        'name' => t('cond_fracture'),
        'icon' => '🩹',
        'badge' => 'Callus Formation & Bone Regeneration',
        'overview_en' => 'Bone fracture healing is an intense metabolic phase requiring massive protein for collagen scaffolding, Zinc for cellular proliferation, Vitamin C for hydroxyproline synthesis, and Calcium for mineral callus hardening.',
        'overview_kn' => 'ಮೂಳೆ ಮುರಿತ ಚೇತರಿಕೆಗೆ ಕೊಲಾಜೆನ್ ರಚನೆಗೆ ಹೆಚ್ಚಿನ ಪ್ರೋಟೀನ್, ವಿಟಮಿನ್ ಸಿ, ಸತು ಮತ್ತು ಕ್ಯಾಲ್ಸಿಯಂ ಅತ್ಯಗತ್ಯವಾಗಿದೆ.',
        'overview_hi' => 'हड्डी टूटने पर नई हड्डी के निर्माण के लिए प्रचुर मात्रा में प्रोटीन, विटामिन सी, जिंक और कैल्शियम की आवश्यकता होती है।',
        'hydration' => '3 Litres / day (Maintains cellular hydration and metabolic transport to injury site)',
        'superfoods' => [
            ['name' => 'Bone Broth (Paya / Soup)', 'kannada' => 'ಬೋನ್ ಬ್ರಾತ್ / ಪಾಯಾ ಸೂಪ್', 'hindi' => 'हड्डियों का सूप (पाया)', 'type' => 'nonveg', 'nutrient' => 'Collagen Peptides & Glycine', 'benefit' => 'Supplies exact raw amino acids needed to rebuild broken bone matrix.'],
            ['name' => 'Sprouted Pulses, Moong & Chickpeas', 'kannada' => 'ಮೊಳಕೆ ಕಾಳುಗಳು ಮತ್ತು ಕಡಲೆ', 'hindi' => 'अंकुरित मूंग, चना और दालें', 'type' => 'veg', 'nutrient' => 'Plant Protein & Zinc', 'benefit' => 'Drives cell mitosis and collagen matrix bridging.'],
            ['name' => 'Citrus Fruits (Oranges, Amla, Guava)', 'kannada' => 'ನೆಲ್ಲಿಕಾಯಿ, ಕಿತ್ತಳೆ, ಸೀಬೆಹಣ್ಣು', 'hindi' => 'आंवला, संतरा, अमरूद', 'type' => 'veg', 'nutrient' => 'Vitamin C (Ascorbic Acid)', 'benefit' => 'Essential co-factor for collagen synthesis and bone callus strength.'],
            ['name' => 'Paneer & Greek Yogurt', 'kannada' => 'ಪನೀರ್ ಮತ್ತು ಮೊಸರು', 'hindi' => 'पनीर और गाढ़ा दही', 'type' => 'veg', 'nutrient' => 'Casein Protein & Phosphorus', 'benefit' => 'Sustained amino acid release for overnight tissue repair.'],
            ['name' => 'Pumpkin Seeds & Cashews', 'kannada' => 'ಕುಂಬಳಕಾಯಿ ಬೀಜ ಮತ್ತು ಗೋಡಂಬಿ', 'hindi' => 'कद्दू के बीज और काजू', 'type' => 'veg', 'nutrient' => 'Zinc & Copper', 'benefit' => 'Stimulates bone alkaline phosphatase enzyme activity.']
        ],
        'avoid' => [
            ['name' => 'Alcoholic Beverages', 'reason' => 'Paralyzes osteoblast growth and significantly delays bone union.'],
            ['name' => 'Excess Sugar & Candy', 'reason' => 'Suppresses immune response and slows down fracture callus formation.'],
            ['name' => 'Excessive Table Salt', 'reason' => 'Causes calcium depletion during the critical mineralisation window.']
        ],
        'meal_plan' => [
            'breakfast' => '2 Boiled eggs / Tofu scramble, 2 slices whole wheat toast, and 1 fresh orange or amla juice.',
            'lunch' => 'Brown rice, thick Chana/Rajma curry, Palak Paneer, and a bowl of fresh salad.',
            'evening' => 'Warm bone broth or vegetable sprouted bean soup with roasted pumpkin seeds.',
            'dinner' => 'Grilled chicken breast or Soya chunk curry with mixed vegetable roti and curd.'
        ]
    ],

    'ligament' => [
        'name' => t('cond_ligament'),
        'icon' => '🏃',
        'badge' => 'Tendon & Ligament Tensile Strength',
        'overview_en' => 'Ligaments (ACL, PCL, ankle sprains) and tendons possess low direct blood supply. Nutrition rich in Vitamin C, gelatin/collagen, anthocyanins, and branched-chain amino acids accelerates soft tissue remodeling and joint stability.',
        'overview_kn' => 'ಸ್ನಾಯುರಜ್ಜು ಮತ್ತು ಲಿಗಮೆಂಟ್ ಗಾಯಗಳಿಗೆ ಕೊಲಾಜೆನ್, ವಿಟಮಿನ್ ಸಿ ಮತ್ತು ಸತು ಸಮೃದ್ಧ ಆಹಾರಗಳು ಶಕ್ತಿ ನೀಡುತ್ತವೆ.',
        'overview_hi' => 'लिगामेंट और टेंडन की मोच में कोलेजन, विटामिन सी और एंटी-ऑक्सीडेंट मांसपेशियों और रेशों को तेजी से जोड़ते हैं।',
        'hydration' => '2.5 - 3 Litres / day (Hydrates connective fascia and prevents tendon stiffness)',
        'superfoods' => [
            ['name' => 'Bell Peppers (Capsicum) & Broccoli', 'kannada' => 'ದಪ್ಪ ಮೆಣಸಿನಕಾಯಿ ಮತ್ತು ಬ್ರೊಕೊಲಿ', 'hindi' => 'शिमला मिर्च और ब्रोकली', 'type' => 'veg', 'nutrient' => 'Vitamin C & Bioflavonoids', 'benefit' => 'Stimulates type I and type III collagen synthesis in tendons.'],
            ['name' => 'Gelatin / Hydrolyzed Collagen', 'kannada' => 'ಜೆಲಾಟಿನ್ / ಕಾಲಾಜೆನ್', 'hindi' => 'जिलेटिन / कोलेजन', 'type' => 'nonveg', 'nutrient' => 'Proline & Hydroxyproline', 'benefit' => 'Directly incorporated into repairing ligament micro-tears.'],
            ['name' => 'Pineapple (Fresh)', 'kannada' => 'ಅನಾನಸ್ ಹಣ್ಣು', 'hindi' => 'ताजा अनानास', 'type' => 'veg', 'nutrient' => 'Bromelain Enzyme', 'benefit' => 'Powerful enzyme that reduces post-injury swelling and hematoma.'],
            ['name' => 'Soybeans & Edamame', 'kannada' => 'ಸೋಯಾಬೀನ್', 'hindi' => 'सोयाबीन और सोया पनीर', 'type' => 'veg', 'nutrient' => 'Leucine & Amino Acids', 'benefit' => 'Prevents muscle atrophy during limb immobilisation.'],
            ['name' => 'Avocados & Olive Oil', 'kannada' => 'ಬೆಣ್ಣೆಹಣ್ಣು (ಅವಕಾಡೊ)', 'hindi' => 'एवोकाडो और जैतून का तेल', 'type' => 'veg', 'nutrient' => 'Vitamin E & Healthy Fats', 'benefit' => 'Protects cell membranes of repairing connective tissues.']
        ],
        'avoid' => [
            ['name' => 'High Fructose Corn Syrup / Sodas', 'reason' => 'Impairs cross-linking of new collagen fibers.'],
            ['name' => 'Deep-fried Street Foods', 'reason' => 'Increases systemic oxidative stress that delays healing.'],
            ['name' => 'Excessive White Sugar', 'reason' => 'Glycation hardens collagen fibers, making repaired tendons stiff.']
        ],
        'meal_plan' => [
            'breakfast' => 'Fresh pineapple slices, Besan chilla (chickpea pancake) with mint chutney, and green tea.',
            'lunch' => 'Steamed brown rice, Soya chunks curry, stir-fried bell peppers, and fresh curd.',
            'evening' => 'Handful of roasted almonds, walnuts, and tender coconut water.',
            'dinner' => 'Clear chicken soup / Lentil soup, multigrain rotis with sauteed green beans and paneer.'
        ]
    ],

    'gout' => [
        'name' => t('cond_gout'),
        'icon' => '🍒',
        'badge' => 'Low-Purine & Uric Acid Control',
        'overview_en' => 'Gout results from needle-like monosodium urate crystal deposits in joints (predominantly big toe, ankle, and knee). Diet must strictly avoid purine-heavy foods while increasing alkalizing foods and tart cherries that promote urinary uric acid excretion.',
        'overview_kn' => 'ಗೌಟ್ ಯೂರಿಕ್ ಆಮ್ಲದ ಹರಳುಗಳು ಕೀಲುಗಳಲ್ಲಿ ಶೇಖರಣೆಯಾಗುವುದರಿಂದ ಉಂಟಾಗುತ್ತದೆ. ಕಡಿಮೆ ಪ್ಯೂರಿನ್ ಆಹಾರಗಳು ಮತ್ತು ಚೆರ್ರಿ ಹಣ್ಣುಗಳು ತುಂಬಾ ಒಳ್ಳೆಯದು.',
        'overview_hi' => 'गाउट जोड़ों में यूरिक एसिड क्रिस्टल जमने से होता है। कम प्यूरिन युक्त भोजन और चेरी यूरिक एसिड को घटाने में मददगार हैं।',
        'hydration' => '3.5 - 4 Litres / day (Crucial: Flushes uric acid through kidneys, preventing crystal formation)',
        'superfoods' => [
            ['name' => 'Dark Sweet Cherries & Tart Cherry Juice', 'kannada' => 'ಚೆರ್ರಿ ಹಣ್ಣುಗಳು', 'hindi' => 'चेरी फल और चेरी का रस', 'type' => 'veg', 'nutrient' => 'Anthocyanins', 'benefit' => 'Clinically proven to lower blood uric acid levels and slash flare-ups by 35%.'],
            ['name' => 'Low-fat Milk & Yogurt', 'kannada' => 'ಕಡಿಮೆ ಕೊಬ್ಬಿನ ಹಾಲು ಮತ್ತು ಮೊಸರು', 'hindi' => 'लो-फैट दूध और दही', 'type' => 'veg', 'nutrient' => 'Orotic Acid & Casein', 'benefit' => 'Promotes uric acid excretion through urine.'],
            ['name' => 'Lemon Water (Alkalizing)', 'kannada' => 'ನಿಂಬೆ ಹಣ್ಣಿನ ನೀರು', 'hindi' => 'नींबू पानी', 'type' => 'veg', 'nutrient' => 'Citric Acid & Vit C', 'benefit' => 'Alkalizes body pH and dissolves urate crystal deposits.'],
            ['name' => 'Coffee (Moderate 1-2 cups)', 'kannada' => 'ಕಾಫಿ (ಮಿತವಾಗಿ)', 'hindi' => 'कॉफी (सीमित मात्रा में)', 'type' => 'veg', 'nutrient' => 'Chlorogenic Acid', 'benefit' => 'Inhibits xanthine oxidase enzyme that synthesizes uric acid.'],
            ['name' => 'High-Water Vegetables (Cucumber, Gourds)', 'kannada' => 'ಸೌತೆಕಾಯಿ ಮತ್ತು ಸೋರೆಕಾಯಿ', 'hindi' => 'खीरा, लौकी और तोरई', 'type' => 'veg', 'nutrient' => 'Water & Potassium', 'benefit' => 'Very low purine, deeply hydrating, kidney-friendly.']
        ],
        'avoid' => [
            ['name' => 'Beer & Hard Alcohol', 'reason' => 'Beer is packed with purines and alcohol blocks kidney uric acid excretion.'],
            ['name' => 'Red Meat & Organ Meats (Liver, Kidney)', 'reason' => 'Highest purine concentration; triggers immediate acute gout flare.'],
            ['name' => 'Shellfish, Prawns, Anchovies, Sardines', 'reason' => 'Extremely high purines that spike blood uric acid.'],
            ['name' => 'High Fructose Corn Syrup & Packed Juices', 'reason' => 'Fructose metabolism in liver rapidly generates uric acid.']
        ],
        'meal_plan' => [
            'breakfast' => 'Warm lemon water, low-fat milk with rolled oats, topped with fresh cherries and berries.',
            'lunch' => 'Vegetable pulav with bottle gourd (lauki), low-fat curd raita, cucumber salad, and Moong dal.',
            'evening' => 'Cup of light black coffee or green tea with roasted murmura (puffed rice).',
            'dinner' => 'Moong dal khichdi with plenty of vegetables, steamed zucchini / pumpkin, and warm water with pinch of lemon.'
        ]
    ],

    'spondylosis' => [
        'name' => t('cond_spondylosis'),
        'icon' => '🧘',
        'badge' => 'Spinal Disc Hydration & Nerve Support',
        'overview_en' => 'Cervical and Lumbar Spondylosis stems from intervertebral disc degeneration, disc dehydration, and nerve impingement. Nutrition rich in Magnesium (muscle relaxant), B-Complex vitamins (nerve myelin sheath support), and cellular disc hydration is essential.',
        'overview_kn' => 'ಸ್ಪಾಂಡಿಲೋಸಿಸ್ ಬೆನ್ನುಮೂಳೆಯ ಡಿಸ್ಕ್ ಸವೆತಕ್ಕೆ ಕಾರಣವಾಗುತ್ತದೆ. ಮೆಗ್ನೀಸಿಯಮ್, ವಿಟಮಿನ್ ಬಿ12 ಮತ್ತು ಸಾಕಷ್ಟು ನೀರು ಬೆನ್ನಿನ ಆರೋಗ್ಯಕ್ಕೆ ಅಗತ್ಯ.',
        'overview_hi' => 'स्पोंडिलोसिस में रीढ़ की हड्डी के डिस्क सूखते और घिसते हैं। मैग्नीशियम, विटामिन बी12 और पर्याप्त पानी रीढ़ की सेहत के लिए जरूरी हैं।',
        'hydration' => '3 Litres / day (Intervertebral discs are 80% water; dehydration causes disc height collapse)',
        'superfoods' => [
            ['name' => 'Bananas & Coconut Water', 'kannada' => 'ಬಾಳೆಹಣ್ಣು ಮತ್ತು ಎಳನೀರು', 'hindi' => 'केला और नारियल पानी', 'type' => 'veg', 'nutrient' => 'Potassium & Magnesium', 'benefit' => 'Relieves spinal muscle spasms, cramps, and nerve irritability.'],
            ['name' => 'Pumpkin Seeds & Almonds', 'kannada' => 'ಕುಂಬಳಕಾಯಿ ಬೀಜ ಮತ್ತು ಬಾದಾಮಿ', 'hindi' => 'कद्दू के बीज और बादाम', 'type' => 'veg', 'nutrient' => 'Magnesium & Zinc', 'benefit' => 'Essential for muscle relaxation around cervical and lumbar vertebrae.'],
            ['name' => 'Fortified Nutritional Yeast & Dairy', 'kannada' => 'ವಿಟಮಿನ್ ಬಿ12 ಭರಿತ ಹಾಲು', 'hindi' => 'विटामिन बी12 युक्त दूध और खाद्य', 'type' => 'veg', 'nutrient' => 'Vitamin B12 & B6', 'benefit' => 'Repairs compressed nerve myelin sheaths, reducing radiating sciatica pain.'],
            ['name' => 'Fresh Ginger & Green Tea', 'kannada' => 'ಶುಂಠಿ ಮತ್ತು ಗ್ರೀನ್ ಟೀ', 'hindi' => 'अदरक और ग्रीन टी', 'type' => 'veg', 'nutrient' => 'Gingerols & EGCG', 'benefit' => 'Blocks inflammatory pathways that irritate spinal nerve roots.'],
            ['name' => 'Makhana (Fox Nuts / Lotus Seeds)', 'kannada' => 'ಮಖಾನಾ', 'hindi' => 'मखाना (कमल के बीज)', 'type' => 'veg', 'nutrient' => 'Calcium & Low Sodium', 'benefit' => 'Light, alkaline, spine-supporting snack that strengthens vertebral bones.']
        ],
        'avoid' => [
            ['name' => 'Excessive Salt / Sodium', 'reason' => 'Causes fluid retention imbalances, exacerbating nerve swelling.'],
            ['name' => 'Refined Flour & Fast Foods', 'reason' => 'Promotes inflammatory cytokines that aggravate disc herniation pain.'],
            ['name' => 'Heavy Late-Night Dinners', 'reason' => 'Leads to poor sleep posture and increased spinal muscle tension.']
        ],
        'meal_plan' => [
            'breakfast' => 'Upma with vegetables & roasted peanuts, 1 banana, and a cup of warm ginger tea.',
            'lunch' => '2 Whole wheat chapattis, Palak/Methi dal, curd, and cucumber-carrot salad.',
            'evening' => 'Roasted Makhana with mild rock salt and coconut water.',
            'dinner' => 'Vegetable vegetable stew with brown rice or idlis, followed by warm turmeric milk with pinch of nutmeg.'
        ]
    ]
];

$active_info = $nutrition_data[$selected_condition];
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars(t('food_title')) ?> | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .condition-pills {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        .cond-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.4rem;
            background: #f1f5f9;
            color: #1e3a5f;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            border: 2px solid transparent;
            transition: all 0.2s ease;
        }
        .cond-btn:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        .cond-btn.active {
            background: #1a3e5c;
            color: #ffffff;
            border-color: #ffb347;
            box-shadow: 0 8px 18px rgba(26, 62, 92, 0.25);
        }

        .food-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.2rem;
            margin-top: 1rem;
        }
        .food-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s;
        }
        .food-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.07);
        }
        .food-card.avoid {
            border-left: 5px solid #ef4444;
            background: #fffafa;
        }
        .food-card.super {
            border-left: 5px solid #10b981;
            background: #f7fdfa;
        }

        .food-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.6rem;
        }
        .food-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a3e5c;
        }
        .food-local-name {
            font-size: 0.9rem;
            color: #0d9488;
            font-weight: 600;
            margin-top: 2px;
        }
        .diet-badge {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            text-transform: uppercase;
        }
        .diet-badge.veg {
            background: #dcfce7;
            color: #15803d;
        }
        .diet-badge.nonveg {
            background: #fee2e2;
            color: #b91c1c;
        }
        .food-nutrient {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            margin-bottom: 0.6rem;
        }
        .food-desc {
            font-size: 0.9rem;
            color: #475569;
            line-height: 1.5;
        }

        .diet-filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            background: #f8fafc;
            padding: 0.8rem 1.2rem;
            border-radius: 20px;
        }
        .filter-links {
            display: flex;
            gap: 0.5rem;
        }
        .filter-link {
            padding: 0.4rem 0.9rem;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
            background: #ffffff;
            border: 1px solid #cbd5e1;
        }
        .filter-link.active {
            background: #2c5f8a;
            color: #ffffff;
            border-color: #2c5f8a;
        }

        .meal-timeline {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .meal-box {
            background: #f8fbff;
            border: 1px solid #dbeafe;
            border-radius: 18px;
            padding: 1.2rem;
        }
        .meal-box h4 {
            color: #1e40af;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
        }

        .print-btn {
            background: #0f766e;
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .print-btn:hover {
            background: #115e59;
        }

        @media print {
            .kiosk-nav-bar, .kiosk-top-bar, .condition-pills, .diet-filter-bar, .print-btn {
                display: none !important;
            }
            body {
                background: white;
                padding: 0;
            }
            .kiosk-container {
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
<div class="kiosk-container">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <div class="main-content">
        <div class="page-title-banner">
            <div>
                <h2>🥗 <?= htmlspecialchars(t('food_title')) ?></h2>
                <p><?= htmlspecialchars(t('food_subtitle')) ?></p>
            </div>
            <button class="print-btn" onclick="window.print()">
                <?= htmlspecialchars(t('print_diet_chart')) ?>
            </button>
        </div>

        <?php if ($latest_ass): ?>
        <div class="alert-box" style="background: #e0f2fe; border-left: 5px solid #0284c7; padding: 0.9rem 1.2rem; border-radius: 14px; margin-bottom: 1.5rem; color: #0369a1;">
            ℹ️ <strong><?= htmlspecialchars(t('condition_auto_detected')) ?>:</strong>
            <?= htmlspecialchars($latest_ass['body_part']) ?> (<?= htmlspecialchars(ucfirst($latest_ass['classification'])) ?> pain)
        </div>
        <?php endif; ?>

        <!-- Condition Selection Pills -->
        <div class="condition-pills">
            <?php foreach ($valid_conditions as $cKey): ?>
                <?php $condData = $nutrition_data[$cKey]; ?>
                <a href="?condition=<?= $cKey ?>&diet=<?= htmlspecialchars($diet_filter) ?>" class="cond-btn <?= $selected_condition === $cKey ? 'active' : '' ?>">
                    <span><?= $condData['icon'] ?></span>
                    <?= htmlspecialchars($condData['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Condition Detail Overview -->
        <div class="card" style="border-top: 5px solid #2c5f8a;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
                <h3 class="card-title" style="margin-bottom: 0; border-left: none; padding-left: 0;">
                    <?= $active_info['icon'] ?> <?= htmlspecialchars($active_info['name']) ?>
                </h3>
                <span style="background: #fef3c7; color: #92400e; font-weight: 700; padding: 0.4rem 0.9rem; border-radius: 20px; font-size: 0.85rem;">
                    <?= htmlspecialchars($active_info['badge']) ?>
                </span>
            </div>

            <p style="font-size: 1.05rem; color: #334155; line-height: 1.7; margin-bottom: 1rem;">
                <?php 
                    $langKey = 'overview_' . getCurrentLang();
                    echo htmlspecialchars($active_info[$langKey] ?? $active_info['overview_en']);
                ?>
            </p>

            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 16px; padding: 0.9rem 1.2rem; display: flex; align-items: center; gap: 0.8rem; color: #065f46;">
                <span style="font-size: 1.5rem;">💧</span>
                <div>
                    <strong><?= htmlspecialchars(t('hydration_title')) ?>:</strong>
                    <span><?= htmlspecialchars($active_info['hydration']) ?></span>
                </div>
            </div>
        </div>

        <!-- Diet Filter & Superfoods to Eat -->
        <div class="card">
            <div class="diet-filter-bar">
                <h3 style="color: #15803d; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <?= htmlspecialchars(t('superfoods_title')) ?>
                </h3>
                <div class="filter-links">
                    <a href="?condition=<?= $selected_condition ?>&diet=all" class="filter-link <?= $diet_filter === 'all' ? 'active' : '' ?>">
                        <?= htmlspecialchars(t('diet_filter_all')) ?>
                    </a>
                    <a href="?condition=<?= $selected_condition ?>&diet=veg" class="filter-link <?= $diet_filter === 'veg' ? 'active' : '' ?>">
                        <?= htmlspecialchars(t('diet_filter_veg')) ?>
                    </a>
                    <a href="?condition=<?= $selected_condition ?>&diet=nonveg" class="filter-link <?= $diet_filter === 'nonveg' ? 'active' : '' ?>">
                        <?= htmlspecialchars(t('diet_filter_nonveg')) ?>
                    </a>
                </div>
            </div>

            <div class="food-grid">
                <?php 
                foreach ($active_info['superfoods'] as $food): 
                    if ($diet_filter === 'veg' && $food['type'] !== 'veg') continue;
                    if ($diet_filter === 'nonveg' && $food['type'] !== 'nonveg') continue;
                ?>
                <div class="food-card super">
                    <div>
                        <div class="food-header">
                            <div>
                                <div class="food-name"><?= htmlspecialchars($food['name']) ?></div>
                                <?php if (getCurrentLang() === 'kn' && !empty($food['kannada'])): ?>
                                    <div class="food-local-name">ಕನ್ನಡ: <?= htmlspecialchars($food['kannada']) ?></div>
                                <?php elseif (getCurrentLang() === 'hi' && !empty($food['hindi'])): ?>
                                    <div class="food-local-name">हिन्दी: <?= htmlspecialchars($food['hindi']) ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="diet-badge <?= $food['type'] ?>"><?= $food['type'] === 'veg' ? 'VEG' : 'NON-VEG' ?></span>
                        </div>
                        <span class="food-nutrient">🔬 <?= htmlspecialchars($food['nutrient']) ?></span>
                        <div class="food-desc"><?= htmlspecialchars($food['benefit']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Foods to Avoid -->
        <div class="card">
            <h3 class="card-title" style="color: #b91c1c; border-left-color: #ef4444;">
                <?= htmlspecialchars(t('avoid_foods_title')) ?>
            </h3>
            <div class="food-grid">
                <?php foreach ($active_info['avoid'] as $badFood): ?>
                <div class="food-card avoid">
                    <div>
                        <div class="food-header">
                            <div class="food-name" style="color: #991b1b;">❌ <?= htmlspecialchars($badFood['name']) ?></div>
                        </div>
                        <div class="food-desc" style="color: #7f1d1d;"><strong>⚠️ Clinical reason:</strong> <?= htmlspecialchars($badFood['reason']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Sample Daily Recovery Meal Plan -->
        <div class="card">
            <h3 class="card-title" style="color: #1e40af; border-left-color: #3b82f6;">
                <?= htmlspecialchars(t('daily_meal_plan')) ?>
            </h3>
            <div class="meal-timeline">
                <div class="meal-box">
                    <h4>🌅 <?= htmlspecialchars(t('breakfast')) ?></h4>
                    <p style="color: #334155;"><?= htmlspecialchars($active_info['meal_plan']['breakfast']) ?></p>
                </div>
                <div class="meal-box">
                    <h4>☀️ <?= htmlspecialchars(t('lunch')) ?></h4>
                    <p style="color: #334155;"><?= htmlspecialchars($active_info['meal_plan']['lunch']) ?></p>
                </div>
                <div class="meal-box">
                    <h4>☕ <?= htmlspecialchars(t('evening_snack')) ?></h4>
                    <p style="color: #334155;"><?= htmlspecialchars($active_info['meal_plan']['evening']) ?></p>
                </div>
                <div class="meal-box">
                    <h4>🌙 <?= htmlspecialchars(t('dinner')) ?></h4>
                    <p style="color: #334155;"><?= htmlspecialchars($active_info['meal_plan']['dinner']) ?></p>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="dashboard.php" class="btn btn-secondary" style="display: inline-block; width: auto; padding: 0.8rem 2rem;">
                🏠 <?= htmlspecialchars(t('back_to_dashboard')) ?>
            </a>
            <a href="body_explorer.php" class="btn btn-primary" style="display: inline-block; width: auto; padding: 0.8rem 2rem; margin-left: 1rem;">
                🦴 <?= htmlspecialchars(t('nav_body_explorer')) ?>
            </a>
        </div>
    </div>
</div>
</body>
</html>
