use std::ops::Range;

use serde::Deserialize;

#[derive(Deserialize)]
#[serde(default)]
pub struct Configuration {
    pub default_gallery_page: i64,
    pub default_gallery_size: i64,
    pub default_gallery_size_range: Range<i64>,

    pub allowed_mime_types: Vec<(String, String)>,

    pub instance_name: String,
    pub instance_description: String,

    pub character_pool: String,
    pub image_id_length: usize,
    pub image_secret_key_length: usize,

    pub allow_gallery: bool,
}

impl Default for Configuration {
    fn default() -> Self {
        Self {
            default_gallery_page: 1,
            default_gallery_size: 25,
            default_gallery_size_range: 1..50,
            allowed_mime_types: vec![("image/png".into(), "png".into())],
            instance_name: "rusti".into(),
            instance_description: "a tiny, anonymous image service".into(),
            allow_gallery: true,
            character_pool: "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789".into(),
            image_id_length: 5,
            image_secret_key_length: 32,
        }
    }
}
