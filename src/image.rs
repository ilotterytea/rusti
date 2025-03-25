use std::fs::{copy, create_dir_all, exists};

use actix_multipart::form::{MultipartForm, tempfile::TempFile, text::Text};
use actix_web::{HttpResponse, web};
use chrono::{Duration, NaiveDateTime, Utc};
use diesel::{ExpressionMethods, QueryDsl, RunQueryDsl, SelectableHelper};
use serde::Deserialize;

use crate::{
    Response,
    config::Configuration,
    database::{
        establish_connection,
        models::{Image, NewImage},
        schema::images::dsl as im,
    },
    random::generate_random_sequence,
};

#[derive(MultipartForm)]
pub struct ImageUpload {
    pub file: TempFile,
    pub expires: Option<Text<i32>>,
    pub visibility: Option<Text<i32>>,
    pub tags: Option<Text<String>>,
    pub password: Option<Text<String>>,
}

#[derive(Deserialize)]
pub struct ImageDeletionQuery {
    pub key: String,
}

pub async fn handle_image_upload(
    config: web::Data<Configuration>,
    MultipartForm(form): MultipartForm<ImageUpload>,
) -> HttpResponse {
    if !exists("userdata").unwrap_or(false) && create_dir_all("userdata").is_err() {
        return HttpResponse::InternalServerError().json(Response {
            status_code: 500,
            message: Some("Failed to create a directory for user data".into()),
            data: None::<Image>,
        });
    }

    let mime = match form.file.content_type {
        Some(m) => m,
        None => {
            return HttpResponse::BadRequest().json(Response {
                status_code: 400,
                message: Some("Unknown MIME type".into()),
                data: None::<Image>,
            });
        }
    };

    let (_, extension) = match config
        .allowed_mime_types
        .iter()
        .find(|(x, _)| x.eq(mime.essence_str()))
    {
        Some(m) => m,
        None => {
            return HttpResponse::BadRequest().json(Response {
                status_code: 400,
                message: Some(format!("Unsupported MIME type: {}", mime.essence_str())),
                data: None::<Image>,
            });
        }
    };

    let id = generate_random_sequence(config.image_id_length, &config.character_pool);

    let old_path = form.file.file.path();
    let new_path = format!("userdata/{}.{}", id, extension);

    if copy(old_path, new_path).is_err() {
        return HttpResponse::InternalServerError().json(Response {
            status_code: 500,
            message: Some("Failed to copy the file".into()),
            data: None::<Image>,
        });
    }

    let filename = match form.file.file_name {
        Some(n) => n,
        None => format!("{}.{}", id, extension),
    };

    // saving image data
    let conn = &mut establish_connection();
    let mut visibility = *form.visibility.unwrap_or(Text(1));

    if !(0..=1).contains(&visibility) {
        visibility = 1;
    }

    let (mut tags, mut password) = (None, None);

    if let Some(t) = form.tags {
        tags = Some(t.clone());
    }

    if let Some(p) = form.password {
        password = Some(p.clone());
    }

    let mut image = diesel::insert_into(im::images)
        .values(NewImage {
            id: &id,
            filename: &filename,
            extension,
            mime: mime.essence_str(),
            secret_key: &generate_random_sequence(
                config.image_secret_key_length,
                &config.character_pool,
            ),
            expires_at: None,
            size: form.file.size as i32,
            visibility,
            tags,
            password,
        })
        .returning(Image::as_returning())
        .get_result(conn)
        .expect("Error saving new image");

    if let Some(expires_at) = form.expires {
        if (1..=6).contains(&*expires_at) {
            let mut expires_at_timestamp: NaiveDateTime = image.uploaded_at.clone();

            match *expires_at {
                2 => expires_at_timestamp += Duration::hours(1),
                3 => expires_at_timestamp += Duration::hours(3),
                4 => expires_at_timestamp += Duration::hours(12),
                5 => expires_at_timestamp += Duration::days(1),
                6 => expires_at_timestamp += Duration::weeks(1),
                _ => {}
            }

            image.expires_at = Some(expires_at_timestamp);

            diesel::update(im::images.find(&image.id))
                .set(im::expires_at.eq(expires_at_timestamp))
                .execute(conn)
                .expect("Error updating an image");
        }
    }

    HttpResponse::Created().json(Response {
        status_code: 201,
        message: None,
        data: Some(image),
    })
}

pub async fn handle_image_retrieve(id: web::Path<String>) -> HttpResponse {
    let conn = &mut establish_connection();

    let image = match im::images.filter(im::id.eq(&*id)).first::<Image>(conn) {
        Ok(i) => i,
        Err(_) => {
            return HttpResponse::NotFound().json(Response {
                status_code: 404,
                message: Some(format!("Image ID {} not found", id)),
                data: None::<Image>,
            });
        }
    };

    let path = format!("userdata/{}.{}", image.id, image.extension);

    if !exists(&path).unwrap_or(false) {
        return HttpResponse::NotFound().json(Response {
            status_code: 404,
            message: Some(format!("Image ID {} not found", id)),
            data: None::<Image>,
        });
    }

    let Ok(data) = std::fs::read(path) else {
        return HttpResponse::InternalServerError().json(Response {
            status_code: 500,
            message: Some("Failed to read image data".into()),
            data: None::<Image>,
        });
    };

    HttpResponse::Ok()
        .insert_header(("Content-Type", image.mime))
        .insert_header((
            "Content-Disposition",
            format!("inline; filename=\"{}\"", image.filename),
        ))
        .body(data)
}

pub async fn handle_image_deletion(
    id: web::Path<String>,
    query: web::Query<ImageDeletionQuery>,
) -> HttpResponse {
    let conn = &mut establish_connection();

    let image = match im::images.filter(im::id.eq(&*id)).first::<Image>(conn) {
        Ok(i) => i,
        Err(_) => {
            return HttpResponse::NotFound().json(Response {
                status_code: 404,
                message: Some(format!("Image ID {} not found", id)),
                data: None::<Image>,
            });
        }
    };

    if image.secret_key.ne(&query.key) {
        return HttpResponse::Unauthorized().json(Response {
            status_code: 401,
            message: Some("Your secret key doesn't match with the image's secret key!".into()),
            data: None::<Image>,
        });
    }

    if std::fs::remove_file(format!("userdata/{}.{}", image.id, image.extension)).is_err() {
        return HttpResponse::InternalServerError().json(Response {
            status_code: 500,
            message: Some("Failed to delete an image from hard drive. Try later.".into()),
            data: None::<Image>,
        });
    }

    diesel::delete(im::images.filter(im::id.eq(&*id)))
        .execute(conn)
        .expect("Error deleting images");

    return HttpResponse::Ok().json(Response {
        status_code: 200,
        message: Some("Successfully deleted the image!".into()),
        data: None::<Image>,
    });
}

pub async fn handle_image_update(
    id: web::Path<String>,
    config: web::Data<Configuration>,
    query: web::Query<ImageDeletionQuery>,
    MultipartForm(form): MultipartForm<ImageUpload>,
) -> HttpResponse {
    let conn = &mut establish_connection();

    let image = match im::images.filter(im::id.eq(&*id)).first::<Image>(conn) {
        Ok(i) => i,
        Err(_) => {
            return HttpResponse::NotFound().json(Response {
                status_code: 404,
                message: Some(format!("Image ID {} not found", id)),
                data: None::<Image>,
            });
        }
    };

    if image.secret_key.ne(&query.key) {
        return HttpResponse::Unauthorized().json(Response {
            status_code: 401,
            message: Some("Your secret key doesn't match with the image's secret key!".into()),
            data: None::<Image>,
        });
    }

    let mime = match form.file.content_type {
        Some(m) => m,
        None => {
            return HttpResponse::BadRequest().json(Response {
                status_code: 400,
                message: Some("Unknown MIME type".into()),
                data: None::<Image>,
            });
        }
    };

    let (_, extension) = match config
        .allowed_mime_types
        .iter()
        .find(|(x, _)| x.eq(mime.essence_str()))
    {
        Some(m) => m,
        None => {
            return HttpResponse::BadRequest().json(Response {
                status_code: 400,
                message: Some(format!("Unsupported MIME type: {}", mime.essence_str())),
                data: None::<Image>,
            });
        }
    };

    // rewriting the image
    if std::fs::remove_file(format!("userdata/{}.{}", image.id, image.extension)).is_err() {
        return HttpResponse::InternalServerError().json(Response {
            status_code: 500,
            message: Some("Failed to delete an image from hard drive. Try later.".into()),
            data: None::<Image>,
        });
    }

    let old_path = form.file.file.path();
    let new_path = format!("userdata/{}.{}", image.id, extension);

    if copy(old_path, new_path).is_err() {
        return HttpResponse::InternalServerError().json(Response {
            status_code: 500,
            message: Some("Failed to copy the file".into()),
            data: None::<Image>,
        });
    }

    let filename = match form.file.file_name {
        Some(n) => n,
        None => format!("{}.{}", id, extension),
    };

    let modified_time = Utc::now().naive_utc();

    let image = diesel::update(im::images.find(&image.id))
        .set((
            im::extension.eq(extension),
            im::mime.eq(mime.essence_str()),
            im::filename.eq(filename),
            im::modified_at.eq(modified_time),
        ))
        .returning(Image::as_returning())
        .get_result(conn)
        .expect("Error updating images");

    return HttpResponse::Ok().json(Response {
        status_code: 200,
        message: Some("Successfully updated the image!".into()),
        data: Some(image),
    });
}
