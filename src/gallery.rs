use actix_web::{HttpResponse, web};
use diesel::{ExpressionMethods, QueryDsl, RunQueryDsl};
use serde::{Deserialize, Serialize};

use crate::{
    Response,
    config::{DEFAULT_GALLERY_PAGE, DEFAULT_GALLERY_SIZE, DEFAULT_GALLERY_SIZE_RANGE},
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

pub async fn get_gallery(query: web::Query<GalleryQuery>) -> HttpResponse {
    let page = query.page.unwrap_or(DEFAULT_GALLERY_PAGE);
    let size = query.size.unwrap_or(DEFAULT_GALLERY_SIZE);

    let images = get_images(page, size);

    HttpResponse::Ok().json(Response {
        status_code: 200,
        message: None,
        data: Some(GalleryResponse { page, size, images }),
    })
}

pub fn get_images(mut page: i64, mut size: i64) -> Vec<Image> {
    if !DEFAULT_GALLERY_SIZE_RANGE.contains(&size) {
        size = size.clamp(
            DEFAULT_GALLERY_SIZE_RANGE.start,
            DEFAULT_GALLERY_SIZE_RANGE.end,
        );
    }

    if page < 1 {
        page = 1;
    }

    page -= 1;

    let conn = &mut establish_connection();

    let images = im::images
        .order_by(im::uploaded_at.desc())
        .limit(size)
        .offset(size * page)
        .get_results(conn)
        .expect("Error loading images");

    images
}
