import { Request, Response } from 'express';
import { MenuItem } from '../models/MenuItem';
import logger from '../utils/logger';

class MenuController {
  static async getMenuItems(req: Request, res: Response) {
    try {
      const { category } = req.query;
      
      let whereClause = {};
      
      if (category) {
        whereClause = { category };
      }
      
      const menuItems = await MenuItem.findAll({ where: whereClause });
      
      logger.info(`Fetched ${menuItems.length} menu items`);
      
      res.json(menuItems);
    } catch (error) {
      logger.error('Get menu items error:', error);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
}

export default MenuController;