use actix_web::{App, HttpServer, web};
use config::Configuration;
use handlebars::Handlebars;
use serde::Serialize;

mod config;
mod database;
mod gallery;
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
    let config: Configuration = match std::fs::read_to_string("rusti.toml") {
        Ok(contents) => {
            toml::from_str::<Configuration>(&contents).expect("Error parsing rusti.toml")
        }
        Err(_) => {
            println!("No rusti.toml configuration file. Loading default values...");
            Configuration::default()
        }
    };

    let config = web::Data::new(config);

    let (host, port) = ("0.0.0.0", 18080);

    println!("Running an image web service on {}:{}!", host, port);

    let mut hb = Handlebars::new();
    view::register_handlebars_templates(&mut hb);

    let hb = web::Data::new(hb);

    HttpServer::new(move || {
        App::new()
            .app_data(config.clone())
            .app_data(hb.clone())
            // frontend
            .route("/", web::get().to(view::get_index_view))
            .route("/gallery", web::get().to(view::get_gallery_view))
            .route(
                "/static/{filename:.*}",
                web::get().to(view::handle_static_file),
            )
            // image management
            .service(
                web::scope("/api").service(
                    web::scope("/image")
                        .route("/gallery", web::get().to(gallery::get_gallery))
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
