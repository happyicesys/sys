package com.servlet;

import java.io.BufferedReader;
import java.io.DataInputStream;
import java.io.DataOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.PrintWriter;
import java.net.HttpURLConnection;
import java.net.URL;
import java.sql.Timestamp;
import java.util.ArrayList;
import java.util.Hashtable;
import java.util.concurrent.TimeUnit;
import java.util.stream.Collector;
import java.util.stream.Collectors;
import java.util.Date;
import java.util.Enumeration;
import java.text.ParseException;
import java.text.SimpleDateFormat;

import javax.servlet.ServletException;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import net.sf.json.JSONArray;
import net.sf.json.JSONObject;
import sun.misc.BASE64Decoder;

import weixin.popular.bean.paymch.Transfers;
import wx.pay.util.PayUtil;
import beans.CustomerBean;
import beans.PortBean;
import beans.TempBean;
import beans.TradeBean;
import beans.UserBean;
import beans.VenderBean;
import beans.VenderLogBean;
import beans.clsFeeBackParaBean;
import beans.clsFromGprs;
import beans.clsGoodsBean;
import beans.clsGroupBean;
import jdk.nashorn.internal.runtime.GlobalConstants;

import com.UpdatePort;
import com.clsConst;
import com.clsFid;
import com.ado.SqlADO;
import com.alipay.AlipayQrcode;
import com.alipay.api.response.AlipayTradePrecreateResponse;
import com.brian.sendmail.SendMail;
import com.sun.xml.internal.bind.v2.runtime.reflect.opt.Const;
import com.tools.ToolBox;

/**
 * Servlet implementation class SetPara
 */
public class SetPara2 extends HttpServlet {
	private static final long serialVersionUID = 1L;

	// private static final int HTTP_INVALID_TYPE =-1;
	// private static final int HTTP_COL_DATA=1;
	// private static final int HTTP_COL_LIST_DATA=2;
	// private static final int HTTP_TRADE_DATA=3;
	// private static final int HTTP_MECHINE_DATA=4;
	// private static final int HTTP_KEY_DATA=5;

	private static final int SLOT_OBJ_SIZE = 16;
	private static final int MAX_SLOT_COUNT = 15;
	private static final int SLOT_ID_OFFSET = 11;

	private static final int TRADE_OBJ_SIZE = 0x14;

	private static final int HTTP_GET = 0x80;
	private static final int HTTP_SET = 0x00;

	private static final String ACK = "OK";
	private static final String NAK = "ERROR";

	/**
	 * @see HttpServlet#HttpServlet()
	 */
	public SetPara2() {
		super();
		// TODO Auto-generated constructor stub
	}

	/**
	 * @see HttpServlet#doGet(HttpServletRequest request, HttpServletResponse
	 *      response)
	 */
	protected void doGet(HttpServletRequest request, HttpServletResponse response)
			throws ServletException, IOException {
		request.setCharacterEncoding(CHAR_CODE);
		response.setCharacterEncoding(CHAR_CODE);
		response.setContentType("text/html; charset=GBK");
		PrintWriter pw = response.getWriter();
		pw.print("TEST_OK");

	}

	final String CHAR_CODE = "GBK";
	final String START_LETTER = "{\"";

	/**
	 * @see HttpServlet#doPost(HttpServletRequest request, HttpServletResponse
	 *      response)
	 */
	protected void doPost(HttpServletRequest request, HttpServletResponse response)
			throws ServletException, IOException {
		// TODO Auto-generated method stub
		request.setCharacterEncoding(CHAR_CODE);
		response.setCharacterEncoding(CHAR_CODE);
		response.setContentType("text/html; charset=GBK");
		PrintWriter pw = response.getWriter();

		InputStream is = request.getInputStream();
		DataInputStream input = new DataInputStream(is);

		byte[] strb = new byte[20480];
		int poststrlen = 0;
		int need_len = request.getContentLength();
		while (poststrlen < need_len) {
			poststrlen += input.read(strb, poststrlen, need_len - poststrlen);
		}
		String ret_str = "1";

		String poststr = new String(strb, 0, poststrlen, CHAR_CODE);

		String[] arrstr = poststr.split("&", 0);

		Hashtable<String, String> hash = new Hashtable<String, String>(2, (float) 0.8);
		for (String string : arrstr) {
			String[] subarrstr = string.split("=", 2);
			if (subarrstr.length >= 2) {
				hash.put(subarrstr[0], subarrstr[1]);
			} else if (subarrstr.length == 1) {
				hash.put(subarrstr[0], null);
			}
		}

		int f = ToolBox.filterInt(hash.get("f"));/* 帧编号，服务器上保存1条最近的编号信息，如果收到的是重复的信息，那么不处理数据，仅仅回传上次发送过的数据 */
		int t = ToolBox.filterInt(hash.get("t"));/* 数据类型 */
		int machineid = ToolBox.filterInt(hash.get("m"));/* 机器编号，必须填写 */
		int gprs = ToolBox.filterInt(hash.get("g"));/* GPRS信号 */
		String p = hash.get("p");
		// System.out.println(p);
		p = p.replaceFirst("!", "=");
		clsFromGprs gprsdata = new clsFromGprs(machineid, f, t, gprs, p);
		VenderLogBean logBean = new VenderLogBean(gprsdata.getStr_content(), null, machineid, poststr, f, t);
		if (!gprsdata.getStr_content().equals("{\"Type\":\"P\"}")) {
			System.out.println(gprsdata.getStr_content());
			executePost(gprsdata.getStr_content());
		}
		logBean.add();/* 添加日志到数据库 */

		VenderBean vb = VenderBean.ChkVender(machineid);
		if (vb != null) {
			String last_ret = vb.ChkFrameRepeat(f, t);
			if (last_ret != null) {
				pw.write(last_ret);
				logBean.setResponse(last_ret);
				logBean.updateResponse();
				return;
			}
		} else {
			vb = SqlADO.getVenderBeanByid(machineid);
			if (vb != null) {
				VenderBean.AddVender(vb);
			}
		}
		if (vb == null) {
			return;
		}

		if (gprsdata.getStr_content().startsWith(START_LETTER)) {
			JSONObject obj = JSONObject.fromObject(gprsdata.getStr_content());
			/* json对象 */
			if (vb != null) {
				SqlADO.ClrOffLineTimes(vb.getId());
				vb.setGprs_Sign(gprsdata.getGprs());
				if (obj != null) {
					if (obj.getString("Type").equals("VENDER")) {
						/* 保存售货机状态数据 */
						SetVend(vb, obj);
					} else if (obj.getString("Type").equals("TRADE")) {
						/* 保存交易记录 */
						SaveTradeObj(vb, obj);
					} else if (obj.getString("Type").equals("H")) {
						/* 获取已经支付的交易订单 */
						/* 交易编号,货道号,商品编号,用户名称,支付方式 */
						TradeBean tradeBean = SqlADO.getSingleTradeFromTemp(vb.getId(), 0, 1);

						if (tradeBean != null) {
							tradeBean.setSendstatus(-1);
							SqlADO.updateTradeBeanTemp(tradeBean);
							ret_str = String.format("HEART%s,%d,%d", tradeBean.getOrderid(),
									tradeBean.getGoodroadid(),
									tradeBean.getTradetype());
							/*
							 * ret_str=String.format("%s,%d,%s,%s,%d", tradeBean.getOrderid(),
							 * tradeBean.getGoodroadid(),
							 * tradeBean.getGoodsName(),
							 * tradeBean.getCardinfo(),
							 * tradeBean.getTradetype()
							 * );
							 */

						} else {
							ret_str = String.format("HEART%d", vb.getFlags1());
						}
					} else if (obj.getString("Type").equals("HT")) {
						/* 专门给自动售票机使用 */
						/* 交易编号,货道号,商品编号,用户名称,支付方式 */
						TradeBean tradeBean = SqlADO.getSingleTradeFromTemp(vb.getId(), 0, 1);

						if (tradeBean != null) {
							tradeBean.setSendstatus(-1);
							SqlADO.updateTradeBeanTemp(tradeBean);
							ret_str = String.format("HEART%s,%d,%d", tradeBean.getOrderid(),
									tradeBean.getGoodroadid(),
									tradeBean.getTradetype());
						} else {
							ret_str = String.format("FLAG%d", vb.getFlags1());
						}
					} else if (obj.get("Type").equals("RELOAD")) {
						/* 终端已经更新货道 */
					} else if (obj.get("Type").equals("REQQR")) {
						/* 请求二维码数据 */
						ret_str = GetQrCodeData(vb, obj);
					} else if (obj.get("Type").equals("TIME")) {
						/* 同步时间 */
						ret_str = "TIME" + ToolBox.getDateTimeString();
						// VenderBean.freshVenderPara();
					} else if (obj.get("Type").equals("STATE")) {
						/* 查询非现金支付出货状态 */
						ret_str = GetTradeState(vb, obj);
					} else if (obj.get("Type").equals("GOODS")) {
						/* 请求商品数据 */
						ret_str = GetgoodsData(vb, obj);
					} else if (obj.get("Type").equals("GOODSCOUNT")) {
						/* 请求商品数据 */
						JSONObject goodsinfo = new JSONObject();
						goodsinfo.put("count", clsGoodsBean.getGoodsBeanCount(vb.getGroupid()));
						goodsinfo.put("updatetime", ToolBox.getDateTimeString());
						ret_str = goodsinfo.toString();
						System.out.println(ret_str);
					} else if (obj.get("Type").equals("CNCL")) {
						/* 用户取消交易,或者交易超时 */
						ret_str = CancelTrade(vb, obj);
					} else if (obj.get("Type").equals("VSWIP")) {
						/*
						 * 刷卡消费
						 * {"Type":"VSWIP","CARD":"12345678","PRICE":1000,"PARA":"12","Vid":1}
						 *
						 */
						ret_str = Vender_MakeSwipTrade(vb, obj);
					} else {

					}
				}
			}
		} else {
			/* 字节对象 */
			byte[] b = gprsdata.getContent();
			if (b != null) {
				int i = 0;
				if (vb != null) {
					SqlADO.ClrOffLineTimes(vb.getId());
					// vb.setGprs_Sign(gprsdata.getGprs());
					if (b[i] == 'S') {
						/* 货道数据 */
						try {
							SaveColListData(b, vb.getId());
						} catch (Exception e) {
							// System.out.println(e);
							e.printStackTrace();
						}
					}
				}
			}
		}

		String base64_str = ToolBox.getBASE64(ret_str);
		base64_str = base64_str.replace("\r", "");
		base64_str = base64_str.replace("\n", "");
		String str = String.format("%d,%d,%s", f, base64_str.length(), base64_str);
		vb.setM_cmdType(t);
		vb.setLast_fid(f);
		vb.setLast_send_string(str);
		logBean.setResponse(str);
		logBean.updateResponse();
		pw.write(str);
	}

	public static String executePost(String urlParameters) {
		HttpURLConnection connection = null;
		String targetURL = "https://sys.happyice.com.sg/api/v1/vend-data";
		try {
			// Create connection
			URL url = new URL(targetURL);
			connection = (HttpURLConnection) url.openConnection();
			connection.setRequestMethod("POST");
			connection.setRequestProperty("Content-Type",
					"application/json");

			connection.setRequestProperty("Content-Length",
					Integer.toString(urlParameters.getBytes().length));
			connection.setRequestProperty("Content-Language", "en-US");

			connection.setUseCaches(false);
			connection.setDoOutput(true);

			// Send request
			DataOutputStream wr = new DataOutputStream(
					connection.getOutputStream());
			wr.writeBytes(urlParameters);
			wr.close();

			// Get Response
			InputStream is = connection.getInputStream();
			BufferedReader rd = new BufferedReader(new InputStreamReader(is));
			StringBuilder response = new StringBuilder(); // or StringBuffer if Java version 5+
			String line;
			while ((line = rd.readLine()) != null) {
				response.append(line);
				response.append('\r');
			}
			rd.close();
			return response.toString();
		} catch (Exception e) {
			e.printStackTrace();
			return null;
		} finally {
			if (connection != null) {
				connection.disconnect();
			}
		}
	}

	private String Vender_MakeSwipTrade(VenderBean vb, JSONObject obj) {
		/*
		 * 售货机刷卡消费
		 * {"Type":"VSWIP","CARD":"12345678","PRICE":1000,"PARA":12,"Vid":1,"CNT":2}
		 *
		 */
		int para = obj.getInt("PARA");
		String Type = obj.getString("Type");
		String Cardinfo = obj.getString("CARD");
		int Price = obj.getInt("PRICE");
		int Vid = obj.getInt("Vid");
		int stock = obj.getInt("CNT");

		CustomerBean c = CustomerBean.getCustomerBeanByCardID(Cardinfo);
		int ret = CustomerBean.CUSTOMER_OK;
		String str_ret;
		if (c == null) {
			ret = CustomerBean.CUSTOMER_INVALID_CARD;
			str_ret = String.format("%d,%d,%s,%d,%d", ret, Price, "0", para, 0);
			return "SWIPE" + str_ret;
		}
		int vender_group = vb.getGroupid();
		if (vender_group != c.getGroupid()) {
			/* 如果该会员和机器不在同一个集团，那么就不允许交易 */
			ret = CustomerBean.CUSTOMER_INVALID_CARD;
			str_ret = String.format("%d,%d,%s,%d,%d", ret, Price, "0", para, 0);
			return "SWIPE" + str_ret;
		}

		ret = c.Cusume(Price);

		// 创建支付定单
		if (ret == CustomerBean.CUSTOMER_OK) {
			TradeBean tb = new TradeBean();
			String tradeid = ToolBox.MakeTradeID(Vid);
			tb.setOrderid(tradeid);
			tb.setGoodmachineid(vb.getId());
			tb.setGoodroadid(para);
			tb.setInneridname(String.format("%d", para));
			tb.setPrice(Price);
			tb.setChangestatus(1);
			tb.setStatus(1);/* 正在处理中 */
			tb.setLiushuiid("处理中...");
			tb.setCardinfo(Cardinfo);
			tb.setTradetype(clsConst.TRADE_TYPE_CARD);
			tb.setMobilephone(c.get_user_id_string());/* 设置唯一识别号 */
			// tb.setPaystatus(paystatus););
			SqlADO.insertTradeObj(tb);
			// 返回内容：结果代码，价格，交易编号，货道编号,卡内余额
			str_ret = String.format("%d,%d,%s,%d,%d", ret, Price, tradeid, para, c.get_user_amount());
		} else {
			str_ret = String.format("%d,%d,%s,%d,%d", ret, Price, "0", para, 0);
		}

		return "SWIPE" + str_ret;
	}

	private String CancelTrade(VenderBean vb, JSONObject obj) {
		String ret_str = "1";
		/* 查询数据库记录，看看是否当前有支付宝或者微信的交易记录 */
		String tradeid = obj.getString("TRADE");

		TradeBean tb = SqlADO.getTradeBean(tradeid);

		if (tb != null) {
			/* 到支付平台查询订单 */

			/* 如果已经支付就准备退款 */

			/* 如果还没有支付就关闭订单 */
		}

		return ret_str;
	}

	private String GetTradeState(VenderBean vb, JSONObject obj) {
		String ret_str;
		/* 查询数据库记录，看看是否当前有支付宝或者微信的交易记录 */
		String tradeid = obj.getString("TRADE");

		/* 查询交易是否已经支付完成traderecordinfo_tem */
		TradeBean tb = SqlADO.getTradeBeanFromTemp(tradeid);
		int paid = 0;/* 设置为没有支付的状态 */
		int paytype = 99;

		if (tb != null) {
			if (tb.getSendstatus() == 0) {
				if (tb.getChangestatus() == 1) {
					// ret_str="TRADE1"++tradeid;/*支付完成*/
					paid = 1;
					paytype = tb.getTradetype();

					/* 只要有过来获取交易状态的,将status字段改一下, */
					tb.setStatus(-2);
					SqlADO.updateTradeBeanTemp(tb);
				}
			} else {
				// ret_str="TRADE3"+tradeid;/*已经出货的交易*/
				paid = 3;
			}
		} else {
			// ret_str="TRADE2"+tradeid;/*不存在的交易*/
			paid = 2;
		}

		ret_str = String.format("TRADE%d%02d%s", paid, paytype, tradeid);
		return ret_str;
	}

	private String GetVerifyTrade(VenderBean vb, JSONObject obj) {
		String ret_str;
		/* 查询数据库记录，看看是否当前有支付宝或者微信的交易记录 */
		String tradeid = obj.getString("VERIFY");

		/* 查询交易是否已经支付完成 */
		TradeBean tb = SqlADO.getTradeBeanFromTemp(tradeid);
		int paid = 0;/* 设置为没有支付的状态 */
		int paytype = 99;
		int slotid = 0;
		if (tb != null) {
			if (tb.getSendstatus() == 0) {
				if (tb.getChangestatus() == 1) {
					// ret_str="TRADE1"++tradeid;/*支付完成*/
					paid = 1;
					paytype = tb.getTradetype();
					slotid = ToolBox.filterHexInt(tb.getInneridname());
				}
			} else {
				// ret_str="TRADE3"+tradeid;/*已经出货的交易*/
				paid = 3;
			}
		} else {
			// ret_str="TRADE2"+tradeid;/*不存在的交易*/
			paid = 2;
		}

		ret_str = String.format("TRADE%d%02d%03d%s", paid, paytype, slotid, tradeid);
		return ret_str;
	}

	private String GetgoodsData(VenderBean vb, JSONObject obj) {
		int page = -1;
		if (obj.containsKey("Page")) {
			page = obj.getInt("Page");
		}
		ArrayList<clsGoodsBean> goodslst = clsGoodsBean.getGoodsBeanLst(page, 50, vb.getGroupid());

		JSONArray json = new JSONArray();
		json.addAll(goodslst);
		return json.toString();
	}

	private String GetQrCodeData(VenderBean vb, JSONObject obj) {
		String ret_str = null;

		int sid = obj.getInt("SId");
		int price = obj.getInt("PRICE");
		int cnt = obj.getInt("CNT");
		String alqrcode = "", wxqrcode = "", altrade = "", wxtrade = "";
		// int payType=obj.getInt("ptype");
		/* 到支付宝 */

		PortBean pb = SqlADO.getPortBean(vb.getId(), sid);
		if (pb == null) {
			ret_str += ",,";
			return ret_str;
		}

		pb.setAmount(cnt);

		if (pb.getPrice() != price) {
			/* 需要更新二维码 */
			pb.setPrice(price);
		}

		clsGroupBean groupBean = clsGroupBean.getGroup(vb.getGroupid());

		/* 每个货道的二维码都刷新一次 */
		altrade = ToolBox.MakeRPID(pb.getMachineid(), pb.getInnerid());
		wxtrade = altrade;
		AlipayTradePrecreateResponse res = AlipayQrcode.MakeQrcode(pb, altrade, groupBean);
		if (res != null) {
			if (res.isSuccess()) {
				// SqlADO.UpdateGoodsPortQrCode(pb.getMachineid(),pb.getGoodsid(),res.getQrCode(),null);
				alqrcode = res.getQrCode();
				pb.setAl_trade(altrade);
				pb.setQrcode(alqrcode);
			}
		}

		String qrcode = PayUtil.MakeWxQrcode(pb, altrade, groupBean);
		wxqrcode = "Invalid Code";
		if (qrcode != null) {
			// SqlADO.UpdateGoodsPortWxQrCode(pb.getMachineid(),pb.getGoodsid(),qrcode,qrcode_img_url);
			wxqrcode = qrcode;
			pb.setWx_qrcode(wxqrcode);
			pb.setWx_trade(wxtrade);
		}

		ret_str = String.format("QRCODE%s,%s,%s,", alqrcode, wxqrcode, altrade);
		// System.out.println(ret_str);

		// 创建订单
		TradeBean tb = new TradeBean();
		tb.setOrderid(altrade);
		tb.setGoodmachineid(pb.getMachineid());
		tb.setGoodroadid(pb.getInnerid());
		tb.setInneridname(pb.getInneridname());
		tb.setPrice(price);
		SqlADO.insertTradeObjToTemp(tb);
		// SqlADO.UpdateGoodsPortAllQrCode(pb);

		return ret_str;
	}

	void SaveTradeObj(VenderBean vb, JSONObject trade) {
		System.out.println("收到一条交易记录" + trade.toString());
		executePost("收到一条交易记录" + trade.toString());

		TradeBean tbBean = new TradeBean();
		int vid = vb.getId();
		tbBean.setGoodmachineid(vid);

		tbBean.setPrice(trade.getInt("Price"));

		tbBean.setCoin_credit(trade.getInt("COINS"));

		tbBean.setBill_credit(trade.getInt("BILLS"));

		tbBean.setGoodroadid(trade.getInt("SId"));
		if (vb.getId_Format().equals("HEX")) {
			tbBean.setInneridname(String.format(clsConst.SLOT_HEX_FORMAT, tbBean.getGoodroadid()));
		} else {
			tbBean.setInneridname(String.format(clsConst.SLOT_FORMAT, tbBean.getGoodroadid()));
		}

		tbBean.setChanges(trade.getInt("CHANEGS"));

		int lid = trade.getInt("TId");
		if (trade.containsKey("ORDRID")) {
			// timestr=trade.getString("TIME");
			tbBean.setOrderid(trade.getString("ORDRID"));
		} else {
			tbBean.setOrderid(ToolBox.MakeTradeID(vid, lid));
		}
		tbBean.setLiushuiid(String.format("%08d", lid));

		// tbBean.setGoodroadid(trade.getInt("SId"));

		if (trade.containsKey("PAY_TYPE"))//
		{
			tbBean.setTradetype(trade.getInt("PAY_TYPE")); /* 支付类型 */
		} else {
			tbBean.setTradetype(clsConst.TRADE_TYPE_CASH); /* 支付类型 */
		}

		// tbBean.setPaystatus(1);/*支付状态*/
		tbBean.setChangestatus(1);/* 支付状态 */

		tbBean.setSendstatus(trade.getInt("ISOK"));/* 出货状态 */
		if (tbBean.getSendstatus() != 0) {
			/* transfor success */
			/* uodate slot */
			SqlADO.SubPortGoods(vid, tbBean.getInneridname(), 1);

		}
		int errcode = trade.getInt("SErr");/* 写入货道故障 */
		tbBean.setSErr(errcode);

		if (errcode != 0) {
			/**/
			SqlADO.updatePortFault(vid, tbBean.getGoodroadid(), 1);
		}

		if (trade.containsKey("GOODS")) {
			// timestr=trade.getString("TIME");
			tbBean.setGoodsName(trade.getString("GOODS"));
		} else {
			PortBean pb = SqlADO.getPortBean(vid, tbBean.getGoodroadid());
			if (pb != null) {
				tbBean.setBalanceQty(pb.getAmount());

				int gid = pb.getGoodsid();
				if (gid == 0) {
					tbBean.setGoodsName("");
				} else {
					clsGoodsBean g = clsGoodsBean.getGoodsBean(gid);
					if (g == null) {
						tbBean.setGoodsName("");
					} else {
						tbBean.setGoodsName(g.getGoodsname());
					}
				}
			}
		}
		String timestr = null;
		if (trade.containsKey("TIME")) {
			timestr = trade.getString("TIME");
		} else {
			timestr = ToolBox.getDateTimeString();
		}

		tbBean.setReceivetime(Timestamp.valueOf(timestr));

		TradeBean temtrade = SqlADO.getTradeBeanFromTemp(tbBean.getOrderid());
		int sourceFlg = 0;
		if (temtrade == null) {
			temtrade = SqlADO.getTradeBean(tbBean.getOrderid());
			sourceFlg = 1;
		}
		if (temtrade == null) {
			SqlADO.insertTradeObj(tbBean);
			sourceFlg = 2;
		} else {
			if (tbBean.getTradetype() != clsConst.TRADE_TYPE_CASH) {
				temtrade.setLiushuiid(tbBean.getLiushuiid());
				temtrade.setSendstatus(tbBean.getSendstatus());
				temtrade.setGoodsName(tbBean.getGoodsName());
				SqlADO.updateTradeBeanTemp(temtrade);
				if (vb.getAuto_refund() == 1) {
					if (tbBean.getSendstatus() == 0) {
						// clsChkVender里面完成退款
						clsFeeBackParaBean.getLst().add(new clsFeeBackParaBean(temtrade, vb, "出货故障自动退款"));
						return;
					}
				}

				// //if(1==1)//这里添加代码用于获取该机器是否允许自动分帐
				// {
				// if(vb.getAdminId()!=0)
				// {
				// UserBean ub=UserBean.getUserBeanById(vb.getAdminId());
				// boolean ret= WxCoporTransfor.CreateTransfor(temtrade,
				// clsGroupBean.getGroup(vb.getGroupid()), ub, 1-0.036);
				//
				// if(ret)
				// {
				// temtrade.setHas_jiesuan(1);
				// }
				// }
				// }

				if (sourceFlg == 0) {
					// 如果数据来源是临时表格，那么就添加一个记录到正式交易表
					SqlADO.insertTradeObj(temtrade);
				} else {
					// 如果数据来源是正式交易表，那么就直接更新正式交易表
					SqlADO.updateTradeBean(temtrade);
				}
				SqlADO.DeleteFromTemp(temtrade);
			}
		}
	}

	private void SetVend(VenderBean vb, JSONObject venderobj) {
		if (venderobj == null) {
			return;
		}
		if (vb != null) {
			int bill_stat = venderobj.getInt("BILLStat");
			int coin_stat = venderobj.getInt("CHGEStat");

			int mdb_flg = 0;
			if ((bill_stat & (1 << 1)) == 0x02) {
				mdb_flg |= VenderBean.MDB_COMMUNICATION_BILL;
			}

			if ((coin_stat & (1 << 1)) == 0x02) {
				mdb_flg |= VenderBean.MDB_COMMUNICATION_COIN;
			}
			vb.setMdbDeviceStatus(mdb_flg); /* 1B,MDB设备状态信息 */

			int fun_flg = 0;

			if ((bill_stat & (1 << 0)) == 0x01) {
				fun_flg |= VenderBean.FUNC_IS_MDB_BILL_VALID;
			}

			if ((coin_stat & (1 << 0)) == 0x01) {
				fun_flg |= VenderBean.FUNC_IS_MDB_COIN_VALID;
			}

			UserBean ub = UserBean.getUserBeanById(vb.getAdminId());
			//
			// if(venderobj.getInt("CoinCnt") < 1600 && vb.getIs_coin_alert_sent() == 0) {
			// vb.setIs_coin_alert_sent(1);
			// SendMail.Send(String.format("Low Coin Lvl - VendID : %d [%s] - Coin: %.2f",
			// vb.getId(), vb.getTerminalName(), venderobj.getInt("CoinCnt")/ 100),
			// String.format("Vending ID: %d "+ System.getProperty("line.separator") +
			// "Vending Name: %s "+ System.getProperty("line.separator") + " Coin: (%.2f)" ,
			// vb.getId(), vb.getTerminalName(), venderobj.getInt("CoinCnt")/ 100));
			// }else {
			// vb.setIs_coin_alert_sent(0);
			// }

			if (venderobj.containsKey("Sensor")) {
				fun_flg |= VenderBean.FUNC_IS_SENSOR_VALID;
				vb.setFlags1(venderobj.getInt("Sensor"));
			}

			if (venderobj.containsKey("TEMP")) {
				fun_flg |= VenderBean.FUNC_IS_TERMPER_VALID;
				vb.setTemperature(venderobj.getInt("TEMP"));
				vb.setPrev_temp(vb.getTemperature());

				if (vb.getTemp_alert() == 1) {
					if (venderobj.getInt("TEMP") > vb.TEMP_ALERT_LIMIT) {

						if (vb.getTemp_alert_loop() == 0) {
							vb.setTemperLoopStartTime(ToolBox.getDateTimeString());
							vb.setTemp_alert_loop(vb.getTemp_alert_loop() + 1);
						}

						if (vb.getTemp_alert_loop() > 0) {
							SimpleDateFormat format = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss");
							long timediff = 0;
							try {
								timediff = format.parse(vb.getTemperUpdateTime()).getTime()
										- format.parse(vb.getTemperLoopStartTime()).getTime();
							} catch (Exception e) {
								e.printStackTrace();
							}
							long timediffmins = TimeUnit.MILLISECONDS.toMinutes(timediff);
							if (timediffmins % vb.TEMP_LOOP_TIMING == 0 && timediffmins != 0) {
								vb.setTemp_alert_loop(vb.getTemp_alert_loop() + 1);
							}
						}

						if (vb.getTemp_alert_loop() >= VenderBean.TEMP_ALERT_LOOP + 1 && vb.getIs_alert_sent() == 0) {
							vb.setIs_alert_sent(1);
							try {
								SendMail.Send(
										String.format("Temp Alert ID: %d [%s], 2 Hours above -11 Celsius [%.1f C]",
												vb.getId(), ToolBox.getDateString(), venderobj.getDouble("TEMP") / 10),
										String.format("Vend ID: %d \r\n %s \r\n Current Temp: (%.1f C)", vb.getId(),
												vb.getTerminalName(), venderobj.getDouble("TEMP") / 10),
										vb.getTempAlertExtraEmails());
							} catch (Exception e) {
								e.printStackTrace();
							}
						}
					} else {
						vb.setTemp_alert_loop(0);
						vb.setIs_alert_sent(0);
						vb.setTemperLoopStartTime(null);
					}

					if (venderobj.getInt("TEMP") > vb.TEMP_LONG_ALERT_LIMIT) {

						if (vb.getLongTempAlertLoop() == 0) {
							vb.setLongTempLoopStarttime(ToolBox.getDateTimeString());
							vb.setLongTempAlertLoop(vb.getLongTempAlertLoop() + 1);
						}

						if (vb.getLongTempAlertLoop() > 0) {
							SimpleDateFormat format = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss");
							long timediff = 0;
							try {
								timediff = format.parse(vb.getTemperUpdateTime()).getTime()
										- format.parse(vb.getLongTempLoopStarttime()).getTime();
							} catch (Exception e) {
								e.printStackTrace();
							}
							long timediffmins = TimeUnit.MILLISECONDS.toMinutes(timediff);
							if (timediffmins % vb.TEMP_LONG_LOOP_TIMING == 0 && timediffmins != 0) {
								vb.setLongTempAlertLoop(vb.getLongTempAlertLoop() + 1);
							}
						}

						if (vb.getLongTempAlertLoop() >= VenderBean.TEMP_LONG_ALERT_LOOP + 1
								&& vb.getLongTempAlertSent() == 0) {
							vb.setLongTempAlertSent(1);
							try {
								SendMail.Send(
										String.format("Temp Alert ID: %d [%s], 5 Hours above -16 Celsius [%.1f C]",
												vb.getId(), ToolBox.getDateString(), venderobj.getDouble("TEMP") / 10),
										String.format("Vend ID: %d \r\n %s \r\n Current Temp: (%.1f C)", vb.getId(),
												vb.getTerminalName(),
												venderobj.getDouble("TEMP") / 10),
										vb.getTempAlertExtraEmails());
							} catch (Exception e) {
								e.printStackTrace();
							}
						}
					} else {
						vb.setLongTempAlertLoop(0);
						vb.setLongTempAlertSent(0);
						vb.setLongTempLoopStarttime(null);
					}

					if (venderobj.getInt("TEMP") > vb.TEMP_REFILL_ALERT_LIMIT) {

						if (vb.getRefillTempAlertLoop() == 0) {
							vb.setRefillTempLoopStarttime(ToolBox.getDateTimeString());
							vb.setRefillTempAlertLoop(vb.getRefillTempAlertLoop() + 1);
						}

						if (vb.getRefillTempAlertLoop() > 0) {
							SimpleDateFormat format = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss");
							long timediff = 0;
							try {
								timediff = format.parse(vb.getTemperUpdateTime()).getTime()
										- format.parse(vb.getRefillTempLoopStarttime()).getTime();
							} catch (Exception e) {
								e.printStackTrace();
							}
							long timediffmins = TimeUnit.MILLISECONDS.toMinutes(timediff);
							if (timediffmins % vb.TEMP_LONG_LOOP_TIMING == 0 && timediffmins != 0) {
								vb.setRefillTempAlertLoop(vb.getRefillTempAlertLoop() + 1);
							}
						}

						if (vb.getRefillTempAlertLoop() >= VenderBean.TEMP_REFILL_ALERT_LOOP + 1
								&& vb.getRefillTempAlertSent() == 0) {
							vb.setRefillTempAlertSent(1);
							try {
								SendMail.Send(
										String.format(
												"Temp Alert ID: %d [%s], 40 mins above -5 : Door not closed [%.1f C]",
												vb.getId(), ToolBox.getDateString(), venderobj.getDouble("TEMP") / 10),
										String.format("Vend ID: %d \r\n %s \r\n Current Temp: (%.1f C)", vb.getId(),
												vb.getTerminalName(), venderobj.getDouble("TEMP") / 10),
										vb.getTempAlertExtraEmails());
							} catch (Exception e) {
								e.printStackTrace();
							}
						}
					} else {
						vb.setRefillTempAlertLoop(0);
						vb.setRefillTempAlertSent(0);
						vb.setRefillTempLoopStarttime(null);
					}
				}

				vb.setTemperUpdateTime(ToolBox.getDateTimeString());
				if ((vb.getTemperature() < 1000) && (vb.getTemperature() > -1000)) {
					TempBean temp = new TempBean();
					temp.setGroupid(vb.getGroupid());
					temp.setTemp(vb.getTemperature());
					temp.setTtime(new Timestamp(System.currentTimeMillis()));
					temp.setVid(vb.getId());
					TempBean.InsertclsGroupBean(temp);
				}
			}

			vb.setIRErrCnt(venderobj.getInt("IRErrCnt"));
			vb.setLstSltE(venderobj.getInt("LstSltE"));

			vb.setCoinAttube(venderobj.getInt("CoinCnt")); /* 4B 硬币桶钱币总额 */

			if (venderobj.containsKey("Ver")) {
				vb.setCode_ver(venderobj.getInt("Ver")); /* 4B 硬币桶钱币总额 */
			}
			if (venderobj.containsKey("BllCnt")) {
				vb.setBills(venderobj.getInt("BllCnt"));
			}

			// 2022-7-2 zhouwenjing add
			if (venderobj.containsKey("t2")) {
				fun_flg |= VenderBean.FUNC_IS_ACB_VALID;
				vb.setTem2(venderobj.getInt("t2"));
			}
			if (venderobj.containsKey("t3")) {
				vb.setTem3(venderobj.getInt("t3"));
			}
			if (venderobj.containsKey("t4")) {
				vb.setTem4(venderobj.getInt("t4"));
			}
			if (venderobj.containsKey("fan")) {
				vb.setFan(venderobj.getInt("fan"));
			}
			if (venderobj.containsKey("door")) {
				vb.setDoorisopen(venderobj.getString("door"));
			}

			vb.setFunction_flg(fun_flg); /* 4B,包含机器状态，设备有效性标示 */
			SqlADO.UpdateMechinePara(vb);
		}
	}

	void SaveColListData(byte[] b, int mid) throws Exception {
		int i = 0, j;
		int num = 0;
		ArrayList<PortBean> plst = new ArrayList<PortBean>();
		JSONObject allChannelJsonObj = new JSONObject();

		if (b == null) {
			throw new Exception("无效参数，null");
		}
		int count = (b.length - 5) / PortBean.SLOT_OBJ_SIZE;// ToolBox.arrbyteToint_Little(b, i, 4);
		if ((b.length - 5) != count * PortBean.SLOT_OBJ_SIZE) {
			throw new Exception("无效参数,lenth=" + (b.length - 5));
		}
		i = 5;

		for (j = 0; j < count; j++) {
			PortBean p = new PortBean();
			JSONObject channelJsonObj = new JSONObject();

			p.setMachineid(mid);

			/* 货道编号 */
			int innerId = ToolBox.arrbyteToint_Little(b, i, 2);
			p.setInnerid(innerId);
			channelJsonObj.put("channel_code", innerId);
			// System.out.println(p.getInnerid());
			i += 2;
			p.setInneridname(String.format(clsConst.SLOT_FORMAT, p.getInnerid()));
			/* 货道故障编号 */

			int errorId = b[i++];
			p.setError_id(errorId);
			channelJsonObj.put("error_code", errorId);
			/* 货道容量 */
			num = b[i++] & 0xff;
			p.setCapacity(num);
			channelJsonObj.put("capacity", num);
			/* 货道库存数量 */
			num = b[i++] & 0xff;
			p.setAmount(num);
			channelJsonObj.put("qty", num);
			/* 货道价格 */
			int price = ToolBox.arrbyteToint_Little(b, i, 4);
			p.setPrice(price);
			channelJsonObj.put("amount", price);
			i += 4;
			/* 货道绑定的商品编号 */
			p.setGoodsid(ToolBox.arrbyteToint_Little(b, i, 2));
			i += 2;
			plst.add(p);
			allChannelJsonObj.put(j, channelJsonObj);
		}

		SqlADO.UpdatePort(plst);

    JSONObject jsonObj = new JSONObject();
		jsonObj.put("Vid", mid);
		jsonObj.put("Type", "CHANNEL");
		jsonObj.put("channels", allChannelJsonObj);

		executePost(jsonObj.toString());
	}
}