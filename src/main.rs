use actix_web::{App, HttpServer, web};
use handlebars::Handlebars;
use serde::Serialize;

mod config;
mod database;
mod image;
mod random;
mod view;

#[derive(Serialize)]
pub struct Response<T> {
    pub status_code: u16,
    pub message: Option<String>,
    pub data: Option<T>,
}

#[actix_web::main]
async fn main() -> std::io::Result<()> {
    let (host, port) = ("0.0.0.0", 18080);

    println!("Running an image web service on {}:{}!", host, port);

    let mut hb = Handlebars::new();
    view::register_handlebars_templates(&mut hb);

    let hb = web::Data::new(hb);

    HttpServer::new(move || {
        App::new()
            .app_data(hb.clone())
            // frontend
            .route("/", web::get().to(view::get_index_view))
            .route(
                "/static/{filename:.*}",
                web::get().to(view::handle_static_file),
            )
            // image management
            .service(
                web::scope("/api").service(
                    web::scope("/image")
            .route("/upload", web::post().to(image::handle_image_upload))
                        .route("/upload/{id}", web::put().to(image::handle_image_update))
                        .route(
                            "/retrieve/{id}",
                            web::get().to(image::handle_image_retrieve),
                        )
                        .route(
                            "/delete/{id}",
                            web::delete().to(image::handle_image_deletion),
                        ),
                ),
            )
    })
    .bind((host, port))?
    .run()
    .await
}
