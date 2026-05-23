import { Model, DataTypes } from 'sequelize';
import sequelize from '../config/database';

class GalleryImage extends Model {
  public id!: number;
  public url!: string;
  public alt_text!: string;
  public readonly created_at!: Date;
}

GalleryImage.init(
  {
    id: {
      type: DataTypes.INTEGER,
      autoIncrement: true,
      primaryKey: true,
    },
    url: {
      type: DataTypes.STRING(255),
      allowNull: false,
    },
    alt_text: {
      type: DataTypes.STRING(255),
      allowNull: false,
    },
    created_at: {
      type: DataTypes.DATE,
      defaultValue: DataTypes.NOW,
    },
  },
  {
    sequelize,
    modelName: 'GalleryImage',
    tableName: 'gallery_images',
    timestamps: false,
  }
);

export default GalleryImage;