import { DataTypes, Model } from 'sequelize';
import sequelize from '../config/database';
import User from './User';

class Reservation extends Model {
  public id!: number;
  public user_id!: number;
  public date!: Date;
  public time!: string;
  public number_of_people!: number;
  public status!: string;
  public readonly created_at!: Date;
}

Reservation.init({
  id: {
    type: DataTypes.INTEGER,
    autoIncrement: true,
    primaryKey: true,
  },
  user_id: {
    type: DataTypes.INTEGER,
    references: {
      model: User,
      key: 'id',
    },
  },
  date: {
    type: DataTypes.DATEONLY,
    allowNull: false,
  },
  time: {
    type: DataTypes.TIME,
    allowNull: false,
  },
  number_of_people: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  status: {
    type: DataTypes.STRING(50),
    defaultValue: 'pending',
  },
  created_at: {
    type: DataTypes.DATE,
    defaultValue: DataTypes.NOW,
  },
}, {
  sequelize,
  modelName: 'Reservation',
  tableName: 'reservations',
  timestamps: false,
});

User.hasMany(Reservation, { foreignKey: 'user_id' });
Reservation.belongsTo(User, { foreignKey: 'user_id' });

export default Reservation;