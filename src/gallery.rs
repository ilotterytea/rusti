use actix_web::{HttpResponse, web};
use diesel::{ExpressionMethods, QueryDsl, RunQueryDsl};
use serde::{Deserialize, Serialize};

use crate::{
    Response,
    config::Configuration,
    database::{establish_connection, models::Image, schema::images::dsl as im},
};

#[derive(Deserialize)]
pub struct GalleryQuery {
    pub page: Option<i64>,
    pub size: Option<i64>,
}

#[derive(Serialize)]
struct GalleryResponse {
    pub page: i64,
    pub size: i64,
    pub images: Vec<Image>,
}

pub async fn get_gallery(
    config: web::Data<Configuration>,
    query: web::Query<GalleryQuery>,
) -> HttpResponse {
    if !config.allow_gallery {
        return HttpResponse::Forbidden().json(Response {
            status_code: 403,
            message: Some("The gallery is not allowed in this instance.".into()),
            data: None::<GalleryResponse>,
        });
    }

    let page = query.page.unwrap_or(config.default_gallery_page);
    let size = query.size.unwrap_or(config.default_gallery_size);

    let images = get_images(&config, page, size);

    HttpResponse::Ok().json(Response {
        status_code: 200,
        message: None,
        data: Some(GalleryResponse { page, size, images }),
    })
}

pub fn get_images(config: &Configuration, mut page: i64, mut size: i64) -> Vec<Image> {
    if !config.allow_gallery {
        return Vec::new();
    }

    if !config.default_gallery_size_range.contains(&size) {
        size = size.clamp(
            config.default_gallery_size_range.start,
            config.default_gallery_size_range.end,
        );
    }

    if page < 1 {
        page = 1;
    }

    page -= 1;

    let conn = &mut establish_connection();

    let images = im::images
        .filter(im::visibility.eq(1))
        .order_by(im::uploaded_at.desc())
        .limit(size)
        .offset(size * page)
        .get_results(conn)
        .expect("Error loading images");

    images
}
