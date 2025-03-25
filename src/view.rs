use std::path::Path;

use actix_web::{HttpResponse, Responder, web};
use diesel::{QueryDsl, RunQueryDsl};
use handlebars::Handlebars;
use serde_json::json;

use crate::{
    config::{DEFAULT_GALLERY_PAGE, DEFAULT_GALLERY_SIZE},
    database::establish_connection,
    gallery::{GalleryQuery, get_images},
};

const STATIC_FILES: include_dir::Dir = include_dir::include_dir!("static");
const TEMPLATE_FILES: include_dir::Dir = include_dir::include_dir!("templates");

pub async fn handle_static_file(filename: web::Path<String>) -> HttpResponse {
    let path = &*filename;

    let Some(file) = STATIC_FILES.get_file(path) else {
        return HttpResponse::NotFound().body(format!("{} not found", path));
    };

    let Some(mime) = mime_guess::from_path(path).first_raw() else {
        return HttpResponse::NotFound().body(format!("{} cannot be sent", path));
    };

    let path = Path::new(path);

    HttpResponse::Ok()
        .insert_header(("Content-Type", mime))
        .insert_header((
            "Content-Disposition",
            format!(
                "inline; filename=\"{}\"",
                path.file_name().unwrap().to_str().unwrap()
            ),
        ))
        .body(file.contents())
}

pub fn register_handlebars_templates(hb: &mut Handlebars<'_>) {
    for template in TEMPLATE_FILES.files() {
        let Some(contents) = template.contents_utf8() else {
            continue;
        };

        let name = template.path().file_name().unwrap().to_str().unwrap();

        hb.register_template_string(name, contents)
            .expect("Error loading template");
    }
}

pub async fn get_index_view(hb: web::Data<Handlebars<'_>>) -> impl Responder {
    let body = hb
        .render(
            "index.hbs",
            &json!({
                "is_home": true,
                "rusti_name": "rusti",
                "rusti_description": "a tiny, anonymous image service",
                "rusti_version": env!("CARGO_PKG_VERSION")
            }),
        )
        .unwrap();

    web::Html::new(body)
}

pub async fn get_gallery_view(
    hb: web::Data<Handlebars<'_>>,
    query: web::Query<GalleryQuery>,
) -> impl Responder {
    let mut page = query.page.unwrap_or(DEFAULT_GALLERY_PAGE);
    let size = query.size.unwrap_or(DEFAULT_GALLERY_SIZE);

    let conn = &mut establish_connection();
    let mut max_pages: i64 = crate::database::schema::images::dsl::images
        .count()
        .get_result(conn)
        .expect("Error counting images");

    max_pages /= size;
    max_pages += 1;

    if page > max_pages {
        page = max_pages;
    }

    let images = get_images(page, size);

    let body = hb
        .render(
            "gallery.hbs",
            &json!({
                "images": images,
                "page": page,
                "max_pages": max_pages,
                "size": size,

                "is_gallery": true,
                "page_title": format!("gallery (page {}/{})", page, max_pages),
                "rusti_name": "rusti",
                "rusti_description": "a tiny, anonymous image service",
                "rusti_version": env!("CARGO_PKG_VERSION")
            }),
        )
        .unwrap();

    web::Html::new(body)
}
