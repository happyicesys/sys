import{T as u,j as f,c as _,w as s,o as a,a as i,u as e,Z as p,f as g,l as h,b as o,d as n,n as y,i as v,e as b}from"./app.d127c760.js";import{_ as x}from"./Button.dc8b7417.js";import{_ as k}from"./Guest.4f9a225d.js";import"./_plugin-vue_export-helper.cdc0426e.js";const w=o("div",{class:"mb-4 text-sm text-gray-600"}," Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another. ",-1),V={key:0,class:"mb-4 font-medium text-sm text-green-600"},B=["onSubmit"],E={class:"mt-4 flex items-center justify-between"},L={__name:"VerifyEmail",props:{status:String},setup(r){const c=r,t=u(),l=()=>{t.post(route("verification.send"))},d=f(()=>c.status==="verification-link-sent");return(m,N)=>(a(),_(k,null,{default:s(()=>[i(e(p),{title:"Email Verification"}),w,d.value?(a(),g("div",V," A new verification link has been sent to the email address you provided during registration. ")):h("",!0),o("form",{onSubmit:b(l,["prevent"])},[o("div",E,[i(x,{class:y({"opacity-25":e(t).processing}),disabled:e(t).processing},{default:s(()=>[n(" Resend Verification Email ")]),_:1},8,["class","disabled"]),i(e(v),{href:m.route("logout"),method:"post",as:"button",class:"underline text-sm text-gray-600 hover:text-gray-900"},{default:s(()=>[n("Log Out")]),_:1},8,["href"])])],40,B)]),_:1}))}};export{L as default};
