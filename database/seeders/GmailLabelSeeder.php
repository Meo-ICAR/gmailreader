<?php

namespace Database\Seeders;

use App\Models\GmailLabel;
use Illuminate\Database\Seeder;

class GmailLabelSeeder extends Seeder
{
    public function run(): void
    {
        // Il tuo JSON esatto
        $json = '[{"id":"CHAT","name":"CHAT","type":"system"},{"id":"SENT","name":"SENT","type":"system"},{"id":"INBOX","name":"INBOX","type":"system"},{"id":"IMPORTANT","name":"IMPORTANT","type":"system"},{"id":"TRASH","name":"TRASH","type":"system"},{"id":"DRAFT","name":"DRAFT","type":"system"},{"id":"SPAM","name":"SPAM","type":"system"},{"id":"CATEGORY_FORUMS","name":"CATEGORY_FORUMS","type":"system"},{"id":"CATEGORY_UPDATES","name":"CATEGORY_UPDATES","type":"system"},{"id":"CATEGORY_PERSONAL","name":"CATEGORY_PERSONAL","type":"system"},{"id":"CATEGORY_PROMOTIONS","name":"CATEGORY_PROMOTIONS","type":"system"},{"id":"CATEGORY_SOCIAL","name":"CATEGORY_SOCIAL","type":"system"},{"id":"YELLOW_STAR","name":"YELLOW_STAR","type":"system"},{"id":"STARRED","name":"STARRED","type":"system"},{"id":"UNREAD","name":"UNREAD","type":"system"},{"id":"Label_1","name":"hassisto@postecert.it","type":"user"},{"id":"Label_1281082451187732514","name":"PRIVACY\/THUNDER","type":"user"},{"id":"Label_2","name":"hassisto@pec.it","type":"user"},{"id":"Label_2242691492415199389","name":"PRIVACY","type":"user"},{"id":"Label_2584996620137516787","name":"PRIVACY\/HOLYDAY","type":"user"},{"id":"Label_3964680780692794255","name":"LAYTON","type":"user"},{"id":"Label_4042510124032419805","name":"PRIVACY\/RACES","type":"user"},{"id":"Label_4507525617555374112","name":"PRIVACY\/PEOPLE","type":"user"},{"id":"Label_4580527210705223612","name":"TIM","type":"user"},{"id":"Label_5512929807649716435","name":"PRIVACY\/PALK","type":"user"},{"id":"Label_6119983706498995731","name":"PRIVACY\/INNOVATIVE","type":"user"},{"id":"Label_742664356353238246","name":"PRIVACY\/PEOPLEGROUP","type":"user"},{"id":"Label_8427762792545883382","name":"PRIVACY\/NEWSENSE-CREDIFACILE","type":"user"},{"id":"Label_8520262511997199398","name":"PRIVACY\/NOEMI","type":"user"}]';

        $labels = json_decode($json, true);

        foreach ($labels as $label) {
            // updateOrCreate evita duplicati se lanci il seeder due volte
            GmailLabel::updateOrCreate(
                ['google_id' => $label['id']],  // Chiave di ricerca
                [
                    'name' => $label['name'],
                    'type' => $label['type']
                ]
            );
        }
    }
}
